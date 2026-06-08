<?php

declare(strict_types=1);

namespace Domain\Catalogue\Repositories;

use Domain\Catalogue\Data\ProductData;
use Domain\Catalogue\Enums\WineColour;
use Domain\Catalogue\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ProductRepository
{
    public function find(int $id): ?ProductData
    {
        return Product::find($id)?->getData();
    }

    public function findByUuid(string $uuid): ?ProductData
    {
        return Product::where('uuid', $uuid)->first()?->getData();
    }

    public function paginate(int $perPage = 24): LengthAwarePaginator
    {
        return Product::orderBy('wine_name')
            ->paginate($perPage)
            ->through(fn (Product $product) => $product->getData());
    }

    /**
     * Sortable columns, mapped to allow-list lookups (never trust raw input).
     */
    public const SORTABLE = ['wine_name', 'producer', 'country', 'vintage', 'unit_price', 'stock'];

    public function search(
        ?string $term = null,
        ?string $country = null,
        ?WineColour $colour = null,
        string $sort = 'wine_name',
        string $direction = 'asc',
        int $perPage = 24,
    ): LengthAwarePaginator {
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'wine_name';
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        return Product::query()
            ->when($term !== null && $term !== '', function ($query) use ($term) {
                $query->where(function ($query) use ($term) {
                    $query->where('wine_name', 'like', "%{$term}%")
                        ->orWhere('producer', 'like', "%{$term}%");
                });
            })
            ->when($country !== null && $country !== '', fn ($query) => $query->where('country', $country))
            ->when($colour !== null, fn ($query) => $query->where('colour', $colour->value))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->through(fn (Product $product) => $product->getData());
    }

    public function count(): int
    {
        return Product::count();
    }

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, ProductData> keyed by product id
     */
    public function findMany(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return Product::whereIn('id', $ids)
            ->get()
            ->mapWithKeys(fn (Product $product) => [$product->id => $product->getData()]);
    }

    /**
     * Distinct, non-empty country names for filter dropdowns.
     *
     * @return array<int, string>
     */
    public function countries(): array
    {
        return Product::query()
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->distinct()
            ->orderBy('country')
            ->pluck('country')
            ->all();
    }

    public function allForMap(): Collection
    {
        return Product::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->map(fn (Product $product) => $product->getData());
    }
}
