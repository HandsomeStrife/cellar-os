<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Domain\Catalogue\Actions\ImportCatalogueWinesAction;
use Domain\Catalogue\Actions\ImportWineFactsAction;
use Domain\Supplier\Actions\ImportListedSuppliersAction;
use Domain\Supplier\Actions\ImportParseProfilesAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Restores the canonical trade data from golden JSON files (wine:export-golden)
 * after a reset. Idempotent: suppliers → wines (re-feeds wine facts) → facts
 * (exact restore of provenance/conflicts, superseding the contributed
 * baselines) → recipes. Zero LLM spend.
 */
class ImportGoldenSnapshot extends Command
{
    protected $signature = 'wine:import-golden {--dir=golden : directory on the private disk}';

    protected $description = 'Restore canonical suppliers/catalogues/recipes/facts from golden JSON files.';

    public function handle(): int
    {
        $dir = trim((string) $this->option('dir'), '/');
        $disk = Storage::disk('local');

        if (! $disk->exists("{$dir}/manifest.json")) {
            $this->error("No golden snapshot at {$disk->path($dir)} — run wine:export-golden first (or push one).");

            return self::FAILURE;
        }

        $read = fn (string $file) => json_decode($disk->get("{$dir}/{$file}") ?: '[]', true) ?: [];

        $suppliers = (new ImportListedSuppliersAction)->execute($read('suppliers.json'));
        $wines = (new ImportCatalogueWinesAction)->execute($read('wines.json'), $suppliers['ids']);
        $facts = (new ImportWineFactsAction)->execute($read('wine-facts.json'));
        $profiles = (new ImportParseProfilesAction)->execute($read('parse-profiles.json'), $suppliers['ids']);

        $this->info(sprintf(
            'Restored %d suppliers, %d wines (%d skipped), %d facts, %d recipes.',
            $suppliers['count'],
            $wines['imported'],
            $wines['skipped'],
            $facts,
            $profiles,
        ));

        return self::SUCCESS;
    }
}
