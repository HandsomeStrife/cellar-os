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
        // Archived wines (dropped out of their supplier's current list) are
        // hidden from every browse surface; direct id/uuid lookups still work
        // so existing inventory/order references render fine.
        return Product::whereNull('archived_at')
            ->orderBy('wine_name')
            ->paginate($perPage)
            ->through(fn (Product $product) => $product->getData());
    }

    /**
     * Sortable columns, mapped to allow-list lookups (never trust raw input).
     */
    public const SORTABLE = ['wine_name', 'producer', 'country', 'vintage', 'unit_price', 'stock'];

    /**
     * @param  array<int, int>|null  $supplierIds  restrict to these suppliers (null = all)
     */
    public function search(
        ?string $term = null,
        ?string $country = null,
        ?WineColour $colour = null,
        string $sort = 'wine_name',
        string $direction = 'asc',
        int $perPage = 24,
        ?array $supplierIds = null,
    ): LengthAwarePaginator {
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'wine_name';
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        return Product::query()
            ->whereNull('archived_at')
            ->when($supplierIds !== null, fn ($query) => $query->whereIn('supplier_id', $supplierIds))
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
        return Product::whereNull('archived_at')->count();
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
     * @param  array<int, int>|null  $supplierIds  restrict to these suppliers (null = all)
     * @return array<int, string>
     */
    public function countries(?array $supplierIds = null): array
    {
        return Product::query()
            ->whereNull('archived_at')
            ->when($supplierIds !== null, fn ($query) => $query->whereIn('supplier_id', $supplierIds))
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->distinct()
            ->orderBy('country')
            ->pluck('country')
            ->all();
    }

    public function allForMap(): Collection
    {
        // The public sourcing map excludes wines from buyers' private suppliers.
        return Product::whereNull('archived_at')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereNotIn('supplier_id', fn ($query) => $query->select('id')
                ->from('suppliers')
                ->whereNotNull('created_by_company_id'))
            ->get()
            ->map(fn (Product $product) => $product->getData());
    }
}
