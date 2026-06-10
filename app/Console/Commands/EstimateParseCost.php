<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Domain\Supplier\Enums\ParseMode;
use Domain\Supplier\Exceptions\ResponseTruncatedException;
use Domain\Supplier\Repositories\SupplierDocumentRepository;
use Domain\Supplier\Repositories\SupplierParseProfileRepository;
use Domain\Supplier\Services\ClaudeClient;
use Domain\Supplier\Services\DocumentTextExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Project the full-run cost of parsing a (large) PDF document BEFORE spending:
 * counts the document's exact input tokens (the count-tokens endpoint is free),
 * runs ONE live mid-document sample chunk per model to measure output density
 * and extraction quality, then extrapolates. Input is exact; output scales with
 * wine density, so treat the projection as ±25%.
 */
class EstimateParseCost extends Command
{
    private const CHUNK_PAGES = 5;

    protected $signature = 'wine:estimate {documentId : supplier_documents.id} {--models=claude-opus-4-8,claude-sonnet-4-6,claude-haiku-4-5 : comma-separated models to compare}';

    protected $description = 'Estimate the cost of fully parsing a supplier document, per model.';

    public function handle(ClaudeClient $claude, DocumentTextExtractor $extractor): int
    {
        $document = (new SupplierDocumentRepository)->find((int) $this->argument('documentId'));

        if ($document === null) {
            $this->error('No supplier_documents row with that id.');

            return self::FAILURE;
        }

        $path = Storage::disk('local')->path($document->storage_path);

        if (strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION)) !== 'pdf') {
            $this->info('Tabular file: parsing costs one small mapping call (~a cent) regardless of row count.');

            return self::SUCCESS;
        }

        // A supplier with a learned pattern recipe parses deterministically.
        $profile = (new SupplierParseProfileRepository)->activeForSupplier(
            $document->supplier_id,
            ParseMode::Document,
            $document->uploaded_by_company_id,
        );

        if ($profile !== null && ($profile->recipe['strategy'] ?? null) === 'pattern') {
            $this->info('This supplier has a learned PATTERN recipe — parsing runs deterministically and costs nothing.');

            return self::SUCCESS;
        }

        $pages = $extractor->pageCount($path);
        $chunks = (int) ceil($pages / self::CHUNK_PAGES);

        $this->info("{$document->file_name}: {$pages} pages → {$chunks} extraction chunks.");
        $this->line('Note: if the layout is machine-parseable, the first analyse writes free pattern rules (~$0.05 study) and the LLM costs below never apply.');

        // Exact input-side text volume (free).
        $fullText = '';
        for ($from = 1; $from <= $pages; $from += 50) {
            $fullText .= $extractor->pageText($path, $from, min($from + 49, $pages));
        }
        $textTokens = $claude->countTokens($fullText);
        $this->line('Document text: '.number_format($textTokens).' tokens (measured, free).');

        // One live mid-document sample chunk per model.
        $sampleFrom = max(1, intdiv($pages, 2));
        $sampleTo = min($sampleFrom + self::CHUNK_PAGES - 1, $pages);
        $samplePages = $sampleTo - $sampleFrom + 1;
        $sampleText = trim($extractor->pageText($path, $sampleFrom, $sampleTo));
        $sampleTextTokens = $claude->countTokens($sampleText);
        $recipe = ['structure' => 'A wine trade price list; wines listed under country/region/producer section headers.'];

        $this->line("Sampling pages {$sampleFrom}-{$sampleTo} live per model…\n");

        $rows = [];
        foreach (array_filter(array_map('trim', explode(',', (string) $this->option('models')))) as $model) {
            if (! isset(ClaudeClient::PRICES[$model])) {
                $this->warn("Skipping unknown model {$model}.");

                continue;
            }

            try {
                // Mirror the real pipeline: a truncated (too-dense) chunk is
                // retried at half the page range.
                $pagesUsed = $samplePages;
                $textUsed = $sampleText;

                try {
                    $result = $claude->extractWines($sampleText, $recipe, [], $model);
                } catch (ResponseTruncatedException) {
                    $halfTo = $sampleFrom + intdiv($samplePages, 2) - 1;
                    $pagesUsed = $halfTo - $sampleFrom + 1;
                    $textUsed = trim($extractor->pageText($path, $sampleFrom, $halfTo));
                    $result = $claude->extractWines($textUsed, $recipe, [], $model);
                }
            } catch (\Throwable $e) {
                $rows[] = [$model, 'ERROR: '.substr($e->getMessage(), 0, 40), '-', '-', '-'];

                continue;
            }

            $usage = $claude->last_usage;
            // Per-chunk prompt overhead = sample input minus the chunk text itself.
            $overhead = max(0, $usage['input'] - $claude->countTokens($textUsed, $model));
            $inputProjected = $textTokens + ($chunks * $overhead);
            $outputProjected = (int) round(($usage['output'] / $pagesUsed) * $pages);

            [$inPrice, $outPrice] = ClaudeClient::PRICES[$model];
            $cost = ($inputProjected / 1_000_000) * $inPrice + ($outputProjected / 1_000_000) * $outPrice;

            // Field coverage: average non-empty fields per extracted wine.
            $wineCount = count($result['wines']);
            $avgFields = $wineCount > 0
                ? round(array_sum(array_map(fn ($w) => count(array_filter($w, fn ($v) => trim((string) $v) !== '')), $result['wines'])) / $wineCount, 1)
                : 0;

            $rows[] = [
                $model,
                "{$wineCount} wines ({$pagesUsed}pp), {$avgFields} fields/wine",
                number_format($inputProjected),
                number_format($outputProjected),
                sprintf('$%.2f', $cost),
            ];
        }

        $this->table(['Model', 'Sample quality', 'Input tokens (proj.)', 'Output tokens (proj.)', 'Full-run cost (±25%)'], $rows);
        $this->line('Input is measured exactly; output is extrapolated from the sample chunk\'s wine density.');

        return self::SUCCESS;
    }
}
