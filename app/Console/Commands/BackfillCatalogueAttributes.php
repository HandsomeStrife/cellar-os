<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Domain\Catalogue\Actions\BackfillCatalogueAttributesAction;
use Domain\Catalogue\Models\Product;
use Illuminate\Console\Command;

/**
 * Fills filterable product columns from authoritative sources (LWIN reference,
 * region->country derivation, geocoding) so the catalogue filters work. Reports
 * fill rates before and after. --dry-run changes nothing.
 */
class BackfillCatalogueAttributes extends Command
{
    protected $signature = 'wine:backfill-attributes {--dry-run : report what would change, write nothing}';

    protected $description = 'Backfill country/region/colour/producer/geo on the catalogue from authoritative sources.';

    public function handle(): int
    {
        $apply = ! $this->option('dry-run');

        $this->line(($apply ? '' : '[dry run] ').'Filterable column fill — before:');
        $this->report();

        $stats = (new BackfillCatalogueAttributesAction)->execute($apply);

        $this->newLine();
        $this->info(sprintf(
            '%s from LWIN: %d wine(s) · country from region: %d · geocoded: %d',
            $apply ? 'Filled' : 'Would fill',
            $stats['lwin'], $stats['country'], $stats['geo'],
        ));

        if ($apply) {
            $this->newLine();
            $this->line('Filterable column fill — after:');
            $this->report();
        }

        return self::SUCCESS;
    }

    private function report(): void
    {
        $total = Product::whereNull('archived_at')->count() ?: 1;
        $fields = [
            'country' => "country IS NOT NULL AND country <> ''",
            'region' => "region IS NOT NULL AND region <> ''",
            'colour' => 'colour IS NOT NULL',
            'producer' => "producer IS NOT NULL AND producer <> ''",
            'geo' => 'latitude IS NOT NULL AND longitude IS NOT NULL',
        ];

        foreach ($fields as $label => $cond) {
            $n = Product::whereNull('archived_at')->whereRaw($cond)->count();
            $this->line(sprintf('  %-10s %5.1f%% (%d)', $label, $n / $total * 100, $n));
        }
    }
}
