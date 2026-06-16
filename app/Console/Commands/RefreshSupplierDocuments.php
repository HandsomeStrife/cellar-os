<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Domain\Catalogue\Actions\ArchiveUnseenProductsAction;
use Domain\Catalogue\Actions\BackfillCatalogueAttributesAction;
use Domain\Supplier\Actions\ApproveAllForDocumentAction;
use Domain\Supplier\Actions\RecordCatalogueCommitAction;
use Domain\Supplier\Actions\RecordDocumentAnalysisAction;
use Domain\Supplier\Actions\SupersedeSupplierDocumentAction;
use Domain\Supplier\Data\SupplierDocumentData;
use Domain\Supplier\Repositories\SupplierDocumentRepository;
use Domain\Supplier\Services\DocumentAnalysisService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Weekly published-list refresh: re-download every current document that has a
 * source_url, compare the SHA-256 against the stored copy, and only on change
 * record a NEW document (the old one is archived with a supersede pointer —
 * kept for history, never deleted). With --process the new edition is parsed
 * immediately (reusing the supplier's saved parse profile, so pattern-mode
 * documents cost nothing); with --approve the unflagged rows are committed to
 * the catalogue (the idempotent upsert refreshes prices/attributes in place).
 *
 * Wines that DROP OUT of a new edition are ARCHIVED (hidden from browse/map,
 * kept for inventory/order references, un-archived if they reappear) via
 * ArchiveUnseenProductsAction — never deleted.
 */
class RefreshSupplierDocuments extends Command
{
    protected $signature = 'wine:refresh-documents
        {--process : analyse changed documents immediately (needs pdftotext; LLM-path documents also need ANTHROPIC_API_KEY)}
        {--approve : after analysis, bulk-approve unflagged rows into the catalogue}
        {--model= : override the Claude model for extraction}
        {--only= : refresh a single document id}';

    protected $description = 'Re-download published supplier lists, SHA-gate them, and re-process changed editions.';

    public function handle(DocumentAnalysisService $service): int
    {
        $documents = (new SupplierDocumentRepository)->refreshable()
            ->when($this->option('only'), fn ($docs) => $docs->where('id', (int) $this->option('only'))->values());

        if ($documents->isEmpty()) {
            $this->info('No current documents carry a source_url — nothing to refresh.');

            return self::SUCCESS;
        }

        $failures = 0;
        $committedAny = false;

        foreach ($documents as $document) {
            $label = "#{$document->id} {$document->file_name}";

            try {
                $body = $this->download((string) $document->source_url);
                $this->guardContentType($body, $document->file_type);
            } catch (\Throwable $e) {
                $this->error("{$label}: download failed — {$e->getMessage()}");
                $failures++;

                continue;
            }

            $sha = hash('sha256', $body);

            if ($sha === $document->content_sha256) {
                $this->line("{$label}: unchanged.");

                continue;
            }

            $new = $this->recordNewEdition($document, $body, $sha);
            $this->info("{$label}: CHANGED — stored new edition as document #{$new->id}, archived #{$document->id}.");

            if (! $this->option('process')) {
                continue;
            }

            try {
                $summary = $service->analyse($new, full: true, model: $this->option('model') ?: null);
                // Record provenance (status + analysis_notes + history note) just
                // like the job and CLI, so a refreshed edition is fully logged.
                (new RecordDocumentAnalysisAction)->execute((int) $new->id, $summary);
                $this->line("  parsed: {$summary['notes']}");
            } catch (\Throwable $e) {
                $this->error("  analysis failed: {$e->getMessage()}");
                $failures++;

                continue;
            }

            if ($this->option('approve')) {
                $approved = (new ApproveAllForDocumentAction)->execute((int) $new->id, skipFlagged: true);

                // Whatever still points at the superseded edition wasn't in
                // the new one — archive it (reversible; reappearing wines
                // un-archive via the upsert).
                $archived = (new ArchiveUnseenProductsAction)->execute((int) $document->id);
                (new RecordCatalogueCommitAction)->execute(
                    $new->supplier_id, $new->file_name, $approved, $archived, refresh: true,
                );
                $committedAny = true;
                $this->line("  approved {$approved} unflagged wine(s) into the catalogue; archived {$archived} dropped-out wine(s).");
            }
        }

        // Re-imports carry the supplier's raw values, which can blank out
        // derived fields (e.g. Farr's CSV has no country column, nulling the
        // region-derived country). Re-run the authoritative backfill so the
        // catalogue filters stay populated after every refresh.
        if ($committedAny) {
            $stats = (new BackfillCatalogueAttributesAction)->execute();
            $this->line(sprintf(
                'Backfilled filterable columns — LWIN: %d · country-from-region: %d · geocoded: %d',
                $stats['lwin'], $stats['country'], $stats['geo'],
            ));
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function download(string $url): string
    {
        // decode_content=false: some trade sites declare bogus encodings that
        // make libcurl error out (Farr's live export sends
        // `Content-Encoding: utf-8`, which is a charset, not an encoding).
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) CellarOS list refresh',
        ])
            ->withOptions(['decode_content' => false])
            ->timeout(300)
            ->retry(2, 5000)
            ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException('HTTP '.$response->status());
        }

        $body = $response->body();

        if ($body === '') {
            throw new \RuntimeException('empty response body');
        }

        return $this->normaliseTextEncoding($body);
    }

    /**
     * Normalise UTF-16 text exports (Farr serves BOM-less UTF-16BE CSV) to
     * UTF-8 so the parsers can read them and the SHA is computed on a stable
     * representation. Binary formats (PDF/xlsx) never look like UTF-16.
     */
    private function normaliseTextEncoding(string $body): string
    {
        if (str_starts_with($body, "\xFF\xFE")) {
            return mb_convert_encoding(substr($body, 2), 'UTF-8', 'UTF-16LE');
        }

        if (str_starts_with($body, "\xFE\xFF")) {
            return mb_convert_encoding(substr($body, 2), 'UTF-8', 'UTF-16BE');
        }

        // BOM-less UTF-16: ASCII text interleaved with null bytes — the null
        // position gives the byte order.
        $head = substr($body, 0, 256);

        if (strlen($head) >= 8 && substr_count($head, "\0") > strlen($head) / 3) {
            $endianness = $head[0] === "\0" ? 'UTF-16BE' : 'UTF-16LE';

            return mb_convert_encoding($body, 'UTF-8', $endianness);
        }

        return $body;
    }

    /**
     * A bot-block or login page served with HTTP 200 must never supersede a
     * good document — check the body actually looks like the expected format.
     */
    private function guardContentType(string $body, ?string $fileType): void
    {
        $ok = match (strtolower((string) $fileType)) {
            'pdf' => str_starts_with($body, '%PDF'),
            'xlsx' => str_starts_with($body, 'PK'),
            'csv', 'txt' => ! str_contains(strtolower(substr($body, 0, 512)), '<html'),
            default => true,
        };

        if (! $ok) {
            throw new \RuntimeException('response body is not a '.$fileType.' (bot block or error page?)');
        }
    }

    private function recordNewEdition(SupplierDocumentData $old, string $body, string $sha): SupplierDocumentData
    {
        $extension = pathinfo($old->file_name, PATHINFO_EXTENSION) ?: 'pdf';
        $base = pathinfo($old->file_name, PATHINFO_FILENAME);

        // Strip any previous refresh stamp so editions don't accumulate suffixes.
        $base = (string) preg_replace('/-\d{4}-\d{2}-\d{2}$/', '', $base);

        $fileName = sprintf('%s-%s.%s', $base, now()->format('Y-m-d'), $extension);
        $storagePath = 'supplier-documents/'.$fileName;

        if (Str::contains($storagePath, ['..'])) {
            throw new \RuntimeException('refusing suspicious storage path: '.$storagePath);
        }

        Storage::disk('local')->put($storagePath, $body);

        return (new SupersedeSupplierDocumentAction)->execute(
            $old,
            fileName: $fileName,
            storagePath: $storagePath,
            fileSize: strlen($body),
            sha256: $sha,
        );
    }
}
