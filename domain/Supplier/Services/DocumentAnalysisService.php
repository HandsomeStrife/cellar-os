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
        private PatternParseService $pattern = new PatternParseService,
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

        return $this->summary(ParseMode::Tabular, $proposed, false, $notes, $model);
    }

    /**
     * @return array{mode: string, stored: int, preview: bool, notes: string}
     */
    private function analyseDocument(SupplierDocumentData $document, string $path, bool $full, ?string $model): array
    {
        $pages = $this->extractor->pageCount($path);

        // A PDF with no text layer is a scan/image — pdftotext yields nothing
        // and the parser would silently produce zero wines. Fail with a clear
        // reason instead (OCR is a future enhancement).
        if (trim($this->extractor->pageText($path, 1, min(3, $pages))) === '') {
            throw new RuntimeException(
                'This PDF appears to be a scanned/image document (no text layer). OCR is not yet supported — please upload a text-based PDF or a spreadsheet.'
            );
        }

        // Recipe: reuse the supplier's, else STUDY the document once. The study
        // first tries to write machine rules (pattern strategy — every later
        // parse is free); only when the layout defeats that does it fall back
        // to an LLM-extraction profile.
        $profile = $this->profiles->activeForSupplier($document->supplier_id, ParseMode::Document, $document->uploaded_by_company_id);
        $recipe = $profile?->recipe ?? [];
        $confidence = $profile?->confidence ?? 0.0;
        $recipeChanged = false;

        if ($recipe === []) {
            [$recipe, $confidence] = $this->study($path, $pages, $model);
            $recipeChanged = true;
        }

        $strategy = $recipe['strategy'] ?? (isset($recipe['rules']) ? 'pattern' : 'llm');

        if ($strategy === 'pattern') {
            $result = $this->patternParse($document, $path, $pages, $recipe, $confidence);

            if ($result !== null) {
                [$proposed, $residue] = $result;
                $this->persist($document, ParseMode::Document, $recipe, $confidence, $proposed, $model, $recipeChanged);

                return $this->summary(
                    ParseMode::Document,
                    $proposed,
                    false,
                    "Pattern-parsed {$pages} page(s) deterministically (no extraction tokens)."
                        .($residue > 0 ? " {$residue} unmatched line(s) skipped." : ''),
                    $model,
                );
            }

            // The learned rules matched nothing on this document — re-study as
            // an LLM-extraction profile instead.
            $derived = $this->claude->deriveProfile($this->profileSample($path, $pages), $model);
            $recipe = ['strategy' => 'llm', 'structure' => $derived['structure'], 'notes' => $derived['notes']];
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

        return $this->summary(ParseMode::Document, $proposed, $preview, $notes, $model);
    }

    /**
     * Study an unseen document once: try to derive machine rules (pattern
     * strategy — re-parses become free); fall back to an LLM-extraction
     * profile when the layout defeats deterministic parsing.
     *
     * @return array{0: array<string, mixed>, 1: float} [recipe, confidence]
     */
    private function study(string $path, int $pages, ?string $model): array
    {
        // Sample coordinate rows from the document's BODY (monster lists
        // front-load contents/index pages that look nothing like the listing).
        $mid = max(1, intdiv($pages, 2));
        $sampleRows = $this->extractor->pageRows($path, $mid, min($mid + 2, $pages));

        if ($sampleRows !== []) {
            $derived = $this->claude->deriveRules($this->pattern->renderForStudy($sampleRows), $model);
            $rules = $this->pattern->sanitise($derived['rules']);

            $usable = $derived['feasible'] && ($rules['zones'] !== [] || $rules['row_regex'] !== '');

            if ($usable) {
                // Dry-run the rules against the sample before adopting them.
                $trial = $this->pattern->parse($sampleRows, $derived['rules']);

                if ($trial['matched'] > 0) {
                    return [
                        ['strategy' => 'pattern', 'rules' => $derived['rules'], 'notes' => $derived['notes']],
                        $derived['confidence'],
                    ];
                }
            }
        }

        $derived = $this->claude->deriveProfile($this->profileSample($path, $pages), $model);

        return [
            ['strategy' => 'llm', 'structure' => $derived['structure'], 'notes' => $derived['notes']],
            $derived['confidence'],
        ];
    }

    /**
     * Opening pages plus one from the middle, for LLM-extraction profiling.
     */
    private function profileSample(string $path, int $pages): string
    {
        $sample = $this->extractor->pageText($path, 1, min(2, $pages));

        if ($pages > 6) {
            $mid = intdiv($pages, 2);
            $sample .= "\n\n[... a page from the middle of the document ...]\n\n"
                .$this->extractor->pageText($path, $mid, $mid);
        }

        return $sample;
    }

    /**
     * Run the stored machine rules over the WHOLE document (deterministic, no
     * tokens — so no preview gating). Returns null when the rules match
     * nothing, signalling the caller to fall back to LLM extraction.
     *
     * @param  array<string, mixed>  $recipe
     * @return array{0: array<int, array{payload: array<string, mixed>, confidence: float, flag: string|null, source_ref?: string}>, 1: int}|null [proposed, residue]
     */
    private function patternParse(SupplierDocumentData $document, string $path, int $pages, array $recipe, float $confidence): ?array
    {
        $proposed = [];
        $residue = 0;
        $matched = 0;
        $state = [];

        for ($from = 1; $from <= $pages; $from += 50) {
            $rows = $this->extractor->pageRows($path, $from, min($from + 49, $pages));
            $result = $this->pattern->parse($rows, (array) ($recipe['rules'] ?? []), $state);
            $state = $result['state'];

            $matched += $result['matched'];
            $residue += $result['residue'];

            foreach ($result['rows'] as $raw) {
                $page = $raw['_page'] ?? null;
                unset($raw['_page']);

                $product = $this->normalise->toProductData($raw, $this->identityMapping(), $document->supplier_id);
                $checked = $this->vet($product, $confidence);

                if ($checked !== null) {
                    $checked['source_ref'] = $page !== null ? 'p'.$page : null;
                    $proposed[] = $checked;
                }
            }
        }

        return $matched === 0 ? null : [$proposed, $residue];
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
    private function summary(ParseMode $mode, array $proposed, bool $preview, string $notes, ?string $model = null): array
    {
        $flagged = count(array_filter($proposed, fn ($r) => $r['flag'] !== null));
        $count = count($proposed);

        // Record the real token spend so users can calibrate future runs.
        $usage = $this->claude->usageTotals();
        $cost = $usage['input'] + $usage['output'] > 0
            ? sprintf(' Tokens: %s in / %s out (~$%.2f).', number_format($usage['input']), number_format($usage['output']), $this->claude->usageCost($model))
            : '';

        return [
            'mode' => $mode->value,
            'stored' => $count,
            'preview' => $preview,
            'notes' => "Parsed {$count} wine(s)".($flagged > 0 ? ", {$flagged} flagged for review" : '').'. '.$notes.$cost,
        ];
    }
}
