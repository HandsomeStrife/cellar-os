<?php

declare(strict_types=1);

namespace Domain\Catalogue\Repositories;

use Domain\Catalogue\Data\LwinData;
use Domain\Catalogue\Models\Lwin;
use Domain\Catalogue\Models\Product;

class LwinRepository
{
    /**
     * LWIN reference rows for a set of products, keyed by product id (only
     * products that carry an LWIN link).
     *
     * @param  array<int, int>  $productIds
     * @return array<int, LwinData>
     */
    public function forProducts(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $links = Product::whereIn('id', $productIds)
            ->whereNotNull('lwin')
            ->pluck('lwin', 'id');

        if ($links->isEmpty()) {
            return [];
        }

        $lwins = Lwin::whereIn('lwin', $links->unique()->values())
            ->get()
            ->keyBy('lwin');

        return $links
            ->map(fn (string $lwin) => ($row = $lwins->get($lwin)) ? LwinData::fromModel($row) : null)
            ->filter()
            ->all();
    }
}
