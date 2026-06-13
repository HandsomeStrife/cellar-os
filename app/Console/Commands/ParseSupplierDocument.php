<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Domain\Supplier\Actions\RecordDocumentAnalysisAction;
use Domain\Supplier\Repositories\ParsedWineRepository;
use Domain\Supplier\Repositories\SupplierDocumentRepository;
use Domain\Supplier\Services\DocumentAnalysisService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Manually run the real portfolio parser against an uploaded document — for
 * eyeballing extraction quality and cost against the example files. Requires a
 * live ANTHROPIC_API_KEY in the environment.
 */
class ParseSupplierDocument extends Command
{
    protected $signature = 'wine:parse {documentId : supplier_documents.id} {--full : run the full extraction (not just a preview)} {--model= : override the Claude model}';

    protected $description = 'Parse a supplier document into proposed catalogue wines.';

    public function handle(DocumentAnalysisService $service): int
    {
        $document = (new SupplierDocumentRepository)->find((int) $this->argument('documentId'));

        if ($document === null) {
            $this->error('No supplier_documents row with that id.');

            return self::FAILURE;
        }

        $this->info("Parsing: {$document->file_name} (supplier #{$document->supplier_id})");

        $summary = $service->analyse(
            $document,
            full: (bool) $this->option('full'),
            model: $this->option('model') ?: null,
        );

        // Persist the outcome (status + analysis_notes + a CRM history note)
        // exactly as the queued job does, so a CLI parse is fully recorded.
        (new RecordDocumentAnalysisAction)->execute($document->id, $summary);

        $this->line('');
        $this->info($summary['notes']);
        $this->line("mode={$summary['mode']} stored={$summary['stored']} preview=".($summary['preview'] ? 'yes' : 'no'));

        $sample = (new ParsedWineRepository)->forDocument($document->id)->take(15);
        $this->table(
            ['Wine', 'Vintage', 'Colour', 'Country', 'Price', 'Flag'],
            $sample->map(fn ($w) => [
                Str::limit($w->payload['wine_name'] ?? '', 40),
                $w->payload['vintage'] ?? '',
                $w->payload['colour'] ?? '',
                $w->payload['country'] ?? '',
                $w->payload['unit_price'] ?? '',
                $w->flag ?? '',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
