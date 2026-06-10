<?php

declare(strict_types=1);

namespace Domain\Supplier\Services;

use Domain\Catalogue\Data\ProductData;
use Domain\Import\Services\NormaliseService;
use Domain\Supplier\Actions\SaveColumnMappingAction;
use Domain\Supplier\Actions\SaveParseProfileAction;
use Domain\Supplier\Actions\StoreParsedWinesAction;
use Domain\Supplier\Data\SupplierDocumentData;
use Domain\Supplier\Enums\ParseMode;
use Domain\Supplier\Exceptions\ResponseTruncatedException;
use Domain\Supplier\Repositories\SupplierParseProfileRepository;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * The single integration boundary for LLM-powered portfolio analysis.
 *
 * Determines how to parse a supplier document, parses it into proposed catalogue
 * rows (the review queue), and stores the parse "recipe" so the next document
 * from the same supplier is parsed faster and more accurately. Both file shapes
 * converge on Import\NormaliseService + the review queue:
 *
 *   - tabular (xlsx/csv): LLM derives a column mapping → NormaliseService per row.
 *   - document (pdf):     LLM derives a structural recipe → chunked extraction,
 *                         section context carried across chunk boundaries.
 */
class DocumentAnalysisService
{
    /** Pages per extraction chunk (keeps each LLM call's output bounded). */
    private const CHUNK_PAGES = 5;

    /** PDFs larger than this only get a first-chunk preview until full run is confirmed. */
    private const PREVIEW_THRESHOLD_PAGES = 12;

    public function __construct(
        private ClaudeClient $claude = new ClaudeClient,
        private DocumentTextExtractor $extractor = new DocumentTextExtractor,
        private NormaliseService $normalise = new NormaliseService,
        private SupplierParseProfileRepository $profiles = new SupplierParseProfileRepository,
    ) {}

    /**
     * @return array{mode: string, stored: int, preview: bool, notes: string}
     */
    public function analyse(SupplierDocumentData $document, bool $full = true, ?string $model = null): array
    {
        $path = Storage::disk('local')->path($document->storage_path);

        if (! is_file($path)) {
            throw new RuntimeException('The uploaded file could not be found on disk.');
        }

        $mode = ParseMode::forFileType($document->file_type, $document->file_name);

        return $mode === ParseMode::Tabular
            ? $this->analyseTabular($document, $path, $model)
            : $this->analyseDocument($document, $path, $full, $model);
    }

    /**
     * @return array{mode: string, stored: int, preview: bool, notes: string}
     */
    private function analyseTabular(SupplierDocumentData $document, string $path, ?string $model): array
    {
        $extension = strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION));
        ['headers' => $headers, 'rows' => $rows] = $this->extractor->tabular($path, $extension);

        if ($headers === [] || $rows === []) {
            throw new RuntimeException('No rows could be read from that file.');
        }

        // Reuse the supplier's recipe if its mapped columns still exist, else
        // derive a fresh mapping from the headers + a sample.
        $profile = $this->profiles->activeForSupplier($document->supplier_id, ParseMode::Tabular, $document->uploaded_by_company_id);
        $mapping = $profile !== null ? (array) ($profile->recipe['mapping'] ?? []) : [];
        $confidence = $profile?->confidence ?? 0.0;
        $notes = 'Reused saved mapping.';
        $recipeChanged = false;

        if (! $this->mappingFits($mapping, $headers)) {
            $derived = $this->claude->deriveMapping($headers, $rows, $model);
            $mapping = $derived['mapping'];
            $confidence = $derived['confidence'];
            $notes = $derived['notes'];
            $recipeChanged = true;
        }

        if (($mapping['wine_name'] ?? null) === null) {
            throw new RuntimeException('Could not identify a wine-name column.');
        }

        $proposed = [];
        foreach ($rows as $i => $row) {
            $product = $this->normalise->toProductData($row, $mapping, $document->supplier_id);
            $checked = $this->vet($product, $confidence);

            if ($checked !== null) {
                $checked['source_ref'] = 'row '.($i + 2); // +1 header, +1 to 1-index
                $proposed[] = $checked;
            }
        }

        $this->persist($document, ParseMode::Tabular, ['mapping' => $mapping], $confidence, $proposed, $model, $recipeChanged);
        // Keep the manual import wizard's remembered mapping in sync.
        (new SaveColumnMappingAction)->execute($document->supplier_id, $mapping);

        return $this->summary(ParseMode::Tabular, $proposed, false, $notes);
    }

    /**
     * @return array{mode: string, stored: int, preview: bool, notes: string}
     */
    private function analyseDocument(SupplierDocumentData $document, string $path, bool $full, ?string $model): array
    {
        $pages = $this->extractor->pageCount($path);

        // Recipe: reuse the supplier's, else profile the first couple of pages.
        $profile = $this->profiles->activeForSupplier($document->supplier_id, ParseMode::Document, $document->uploaded_by_company_id);
        $recipe = $profile?->recipe ?? [];
        $confidence = $profile?->confidence ?? 0.0;
        $recipeChanged = false;

        if ($recipe === []) {
            // Sample the opening pages PLUS one from the middle — monster lists
            // often front-load a contents/producer index that looks nothing like
            // the actual wine listing.
            $sample = $this->extractor->pageText($path, 1, min(2, $pages));

            if ($pages > 6) {
                $mid = intdiv($pages, 2);
                $sample .= "\n\n[... a page from the middle of the document ...]\n\n"
                    .$this->extractor->pageText($path, $mid, $mid);
            }

            $derived = $this->claude->deriveProfile($sample, $model);
            $recipe = ['structure' => $derived['structure'], 'notes' => $derived['notes']];
            $confidence = $derived['confidence'];
            $recipeChanged = true;
        }

        // Large documents only get a first-chunk preview until the full run is
        // confirmed from the review screen.
        $preview = ! $full && $pages > self::PREVIEW_THRESHOLD_PAGES;
        $lastPage = $preview ? min(self::CHUNK_PAGES, $pages) : $pages;

        $proposed = [];
        $carry = [];

        for ($from = 1; $from <= $lastPage; $from += self::CHUNK_PAGES) {
            $to = min($from + self::CHUNK_PAGES - 1, $lastPage);
            $this->extractRange($path, $from, $to, $recipe, $carry, $model, $confidence, $document->supplier_id, $proposed);
        }

        // A preview whose opening chunk found nothing (a contents/index section)
        // is useless for judging quality — preview a mid-document chunk instead.
        if ($preview && $proposed === [] && $pages > self::CHUNK_PAGES) {
            $midFrom = max(1, intdiv($pages, 2));
            $this->extractRange($path, $midFrom, min($midFrom + self::CHUNK_PAGES - 1, $pages), $recipe, $carry, $model, $confidence, $document->supplier_id, $proposed);
        }

        $this->persist($document, ParseMode::Document, $recipe, $confidence, $proposed, $model, $recipeChanged);

        $notes = $preview
            ? 'Preview of pages 1-'.$lastPage." (of {$pages}). Approve to import, or run the full extraction."
            : "Extracted across {$pages} page(s).";

        return $this->summary(ParseMode::Document, $proposed, $preview, $notes);
    }

    /**
     * Extract one page range; if the model's output is truncated (chunk too
     * dense — e.g. a Raeburn-style 5,000-line list), split the range in half
     * and retry, down to single pages.
     *
     * @param  array<string, mixed>  $recipe
     * @param  array<string, string>  $carry
     * @param  array<int, array{payload: array<string, mixed>, confidence: float, flag: string|null, source_ref?: string}>  $proposed
     */
    private function extractRange(
        string $path,
        int $from,
        int $to,
        array $recipe,
        array &$carry,
        ?string $model,
        float $confidence,
        int $supplierId,
        array &$proposed,
    ): void {
        $text = trim($this->extractor->pageText($path, $from, $to));

        if ($text === '') {
            return;
        }

        try {
            $result = $this->claude->extractWines($text, $recipe, $carry, $model);
        } catch (ResponseTruncatedException $e) {
            if ($from >= $to) {
                throw $e; // even a single page is too dense — surface it
            }

            $mid = intdiv($from + $to, 2);
            $this->extractRange($path, $from, $mid, $recipe, $carry, $model, $confidence, $supplierId, $proposed);
            $this->extractRange($path, $mid + 1, $to, $recipe, $carry, $model, $confidence, $supplierId, $proposed);

            return;
        }

        $carry = $result['section'] !== [] ? $result['section'] : $carry;

        foreach ($result['wines'] as $raw) {
            $product = $this->normalise->toProductData($raw, $this->identityMapping(), $supplierId);
            $checked = $this->vet($product, $confidence);

            if ($checked !== null) {
                $checked['source_ref'] = $from === $to ? "p{$from}" : "p{$from}-{$to}";
                $proposed[] = $checked;
            }
        }
    }

    /**
     * Per-row safety vetting. Returns a proposed-wine record, or null to drop.
     *
     * @return array{payload: array<string, mixed>, confidence: float, flag: string|null}|null
     */
    private function vet(?ProductData $product, float $confidence): ?array
    {
        if ($product === null || trim($product->wine_name) === '') {
            return null; // no wine name → not a wine
        }

        $flag = null;
        $price = $product->unit_price !== null ? (float) $product->unit_price : null;

        // Drop rows that are plainly a section header, not a wine. Anything
        // merely heading-LIKE is kept but flagged — a human decides (trade
        // lists legitimately set producer/wine names in caps).
        if ($price === null && $this->isHeadingWord($product->wine_name)) {
            return null;
        }

        if ($price !== null && ($price <= 0 || $price > 100000)) {
            $flag = 'suspicious_price';
        } elseif ($price === null && $this->looksLikeHeading($product->wine_name)) {
            $flag = 'suspected_heading';
        } elseif ($price === null) {
            $flag = 'missing_price';
        } elseif ($confidence > 0 && $confidence < 0.6) {
            $flag = 'low_confidence';
        }

        return [
            'payload' => $product->toArray(),
            'confidence' => $confidence,
            'flag' => $flag,
        ];
    }

    /** A bare country/region/colour word with no other signal IS a heading. */
    private function isHeadingWord(string $name): bool
    {
        return in_array(mb_strtolower(trim($name)), [
            'france', 'italy', 'spain', 'portugal', 'germany', 'austria', 'australia',
            'new zealand', 'argentina', 'chile', 'south africa', 'usa', 'united states',
            'red', 'white', 'rosé', 'rose', 'sparkling', 'burgundy', 'bordeaux', 'rhone',
        ], true);
    }

    /** Heading-like (short, all-caps, no digits) — flagged for review, not dropped. */
    private function looksLikeHeading(string $name): bool
    {
        $name = trim($name);

        return $name !== ''
            && mb_strtoupper($name) === $name
            && ! preg_match('/\d/', $name)
            && count(preg_split('/\s+/u', $name) ?: []) <= 2;
    }

    /**
     * @param  array<string, string>  $mapping
     * @param  array<int, string>  $headers
     */
    private function mappingFits(array $mapping, array $headers): bool
    {
        if ($mapping === [] || ! isset($mapping['wine_name'])) {
            return false;
        }

        foreach ($mapping as $header) {
            if (! in_array($header, $headers, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Identity mapping so NormaliseService can consume the LLM's already-keyed rows.
     *
     * @return array<string, string>
     */
    private function identityMapping(): array
    {
        return array_combine(ClaudeClient::FIELDS, ClaudeClient::FIELDS);
    }

    /**
     * @param  array<string, mixed>  $recipe
     * @param  array<int, array{payload: array<string, mixed>, confidence: float, flag: string|null, source_ref?: string}>  $proposed
     */
    private function persist(SupplierDocumentData $document, ParseMode $mode, array $recipe, float $confidence, array $proposed, ?string $model, bool $recipeChanged): void
    {
        if ($document->id === null) {
            throw new RuntimeException('Cannot persist analysis for an unsaved document.');
        }

        // Only write a new profile row when the recipe was actually (re)derived
        // — a clean reuse shouldn't churn the table.
        if ($recipeChanged) {
            (new SaveParseProfileAction)->execute(
                supplierId: $document->supplier_id,
                mode: $mode,
                recipe: $recipe,
                model: $model ?: (string) config('services.anthropic.model'),
                confidence: $confidence,
                sourceDocumentId: $document->id,
                companyId: $document->uploaded_by_company_id,
            );
        }

        (new StoreParsedWinesAction)->execute($document->id, $document->supplier_id, $proposed);
    }

    /**
     * @param  array<int, array{flag: string|null}>  $proposed
     * @return array{mode: string, stored: int, preview: bool, notes: string}
     */
    private function summary(ParseMode $mode, array $proposed, bool $preview, string $notes): array
    {
        $flagged = count(array_filter($proposed, fn ($r) => $r['flag'] !== null));
        $count = count($proposed);

        return [
            'mode' => $mode->value,
            'stored' => $count,
            'preview' => $preview,
            'notes' => "Parsed {$count} wine(s)".($flagged > 0 ? ", {$flagged} flagged for review" : '').'. '.$notes,
        ];
    }
}
