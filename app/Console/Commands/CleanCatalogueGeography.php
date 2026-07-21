<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Domain\Catalogue\Actions\CleanCatalogueGeographyAction;
use Domain\Catalogue\Models\Product;
use Illuminate\Console\Command;

/**
 * Repairs geography columns that make the catalogue filters confusing: country
 * names sitting in the region filter, and junk/duplicated country values. See
 * CleanCatalogueGeographyAction. --dry-run reports the counts, writes nothing.
 */
class CleanCatalogueGeography extends Command
{
    protected $signature = 'wine:clean-geography {--dry-run : report what would change, write nothing}';

    protected $description = 'Clean up region/country columns so the catalogue filters read correctly.';

    public function handle(): int
    {
        $apply = ! $this->option('dry-run');

        $this->line(($apply ? '' : '[dry run] ').'Distinct filter values — before:');
        $this->report();

        $stats = (new CleanCatalogueGeographyAction)->execute($apply);

        $this->newLine();
        $this->info(sprintf(
            '%s: %d non-wine header(s) archived · region demoted %d, cleared %d · country filled %d, region recovered %d, canonicalised %d',
            $apply ? 'Cleaned' : 'Would clean',
            $stats['archived'], $stats['region_demoted'], $stats['region_cleared'],
            $stats['country_filled'], $stats['region_recovered'], $stats['country_canonicalised'],
        ));

        if ($apply) {
            $this->newLine();
            $this->line('Distinct filter values — after:');
            $this->report();
        }

        return self::SUCCESS;
    }

    private function report(): void
    {
        foreach (['country', 'region', 'sub_region'] as $column) {
            $n = Product::whereNull('archived_at')
                ->whereNotNull($column)->where($column, '<>', '')
                ->distinct()->count($column);
            $this->line(sprintf('  %-11s %d distinct value(s)', $column, $n));
        }
    }
}
