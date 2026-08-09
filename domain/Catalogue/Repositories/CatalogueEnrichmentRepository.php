<?php

declare(strict_types=1);

namespace Domain\Catalogue\Repositories;

use Domain\Catalogue\Data\ProductData;
use Domain\Catalogue\Support\WineIdentity;

/**
 * Gap-fills the attributes a supplier's list didn't carry, for display only —
 * products are never mutated by this.
 *
 * The supplier's OWN data always wins; enrichment only ever fills a hole. LWIN
 * reference data (curated, from Liv-ex) fills first, then other vendors' facts
 * fill what remains. Each fill carries its source so the UI can say where the
 * value came from, and contested fields (suppliers who disagree) are withheld
 * entirely rather than guessed at.
 *
 * Shared by every surface that shows wine attributes — the catalogue and the
 * inventory — so a wine reads the same wherever you meet it.
 */
class CatalogueEnrichmentRepository
{
    /**
     * @param  array<int, ProductData>  $products
     * @return array<int, array<string, array{value: mixed, source: string}>> product id => field => fill
     */
    public function forProducts(array $products): array
    {
        if ($products === []) {
            return [];
        }

        $keys = [];
        foreach ($products as $product) {
            $key = WineIdentity::keyFor($product->producer, $product->wine_name);
            if ($key !== null) {
                $keys[$product->id] = $key;
            }
        }

        $facts = (new WineFactRepository)->forIdentities(array_values($keys));
        $lwins = (new LwinRepository)->forProducts(array_map(fn (ProductData $p) => $p->id, $products));

        $enriched = [];

        foreach ($products as $product) {
            $fill = [];

            $lwin = $lwins[$product->id] ?? null;
            if ($lwin !== null) {
                if ($product->colour === null && $lwin->colour !== null) {
                    $fill['colour'] = ['value' => $lwin->colour, 'source' => 'lwin'];
                }
                if (($product->country ?? '') === '' && ($lwin->country ?? '') !== '') {
                    $fill['country'] = ['value' => $lwin->country, 'source' => 'lwin'];
                }
                if (($product->region ?? '') === '' && ($lwin->region ?? '') !== '') {
                    $fill['region'] = ['value' => $lwin->region, 'source' => 'lwin'];
                }
            }

            $fact = $facts[$keys[$product->id] ?? ''] ?? null;
            if ($fact !== null) {
                // Contested fields (suppliers disagree) are withheld entirely.
                $usable = fn (string $field) => ! in_array($field, $fact->conflicted_fields, true);

                if (($product->grape ?? []) === [] && ($fact->grape ?? []) !== [] && $usable('grape')) {
                    $fill['grape'] = ['value' => $fact->grape, 'source' => 'vendor'];
                }
                if (! isset($fill['colour']) && $product->colour === null && $fact->colour !== null && $usable('colour')) {
                    $fill['colour'] = ['value' => $fact->colour, 'source' => 'vendor'];
                }
                if (! isset($fill['country']) && ($product->country ?? '') === '' && ($fact->country ?? '') !== '' && $usable('country')) {
                    $fill['country'] = ['value' => $fact->country, 'source' => 'vendor'];
                }
                if (! isset($fill['region']) && ($product->region ?? '') === '' && ($fact->region ?? '') !== '' && $usable('region')) {
                    $fill['region'] = ['value' => $fact->region, 'source' => 'vendor'];
                }
            }

            if ($fill !== []) {
                $enriched[$product->id] = $fill;
            }
        }

        return $enriched;
    }
}
