<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Domain\Catalogue\Actions\ContributeWineFactsAction;
use Domain\Catalogue\Models\Product;
use Domain\Catalogue\Repositories\WineFactRepository;
use Illuminate\Console\Command;

/**
 * Seeds the shared wine-facts store from products that existed before the
 * facts pipeline (new imports contribute automatically). Idempotent:
 * fill-don't-overwrite semantics mean re-running never clobbers anything.
 */
class BackfillWineFacts extends Command
{
    protected $signature = 'wine:facts-backfill';

    protected $description = 'Populate wine_facts from the existing product catalogue.';

    public function handle(): int
    {
        $contribute = new ContributeWineFactsAction;
        $count = 0;

        Product::query()->orderBy('id')->chunkById(200, function ($products) use ($contribute, &$count) {
            foreach ($products as $product) {
                $contribute->execute($product->getData());
                $count++;
            }
        });

        $this->info("Contributed facts from {$count} product(s); wine_facts now holds ".(new WineFactRepository)->count().' identities.');

        return self::SUCCESS;
    }
}
