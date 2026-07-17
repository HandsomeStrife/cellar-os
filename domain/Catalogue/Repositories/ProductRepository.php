<?php

declare(strict_types=1);

namespace Domain\Catalogue\Repositories;

use Domain\Catalogue\Data\ProductData;
use Domain\Catalogue\Enums\WineColour;
use Domain\Catalogue\Models\Product;
use Illuminate\Database\Eloquent\Builder;
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
        return $this->applyCellarOrder(Product::whereNull('archived_at'))
            ->paginate($perPage)
            ->through(fn (Product $product) => $product->getData());
    }

    /**
     * Sortable columns, mapped to allow-list lookups (never trust raw input).
     */
    public const SORTABLE = ['wine_name', 'producer', 'country', 'region', 'sub_region', 'vintage', 'unit_price'];

    /**
     * The default "cellar list" ordering: wines grouped by type in the trade's
     * conventional sequence (sparkling → white → rosé → orange → red → the
     * rest), then by country, region and sub-region within each type.
     */
    public const DEFAULT_SORT = 'cellar';

    /**
     * @param  array<int, int>|null  $supplierIds  restrict to these suppliers (null = all)
     */
    public function search(
        ?string $term = null,
        ?string $country = null,
        ?WineColour $colour = null,
        ?string $region = null,
        ?string $subRegion = null,
        ?string $producer = null,
        ?string $grape = null,
        ?float $priceMin = null,
        ?float $priceMax = null,
        ?int $vintageMin = null,
        ?int $vintageMax = null,
        string $sort = self::DEFAULT_SORT,
        string $direction = 'asc',
        int $perPage = 24,
        ?array $supplierIds = null,
    ): LengthAwarePaginator {
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : self::DEFAULT_SORT;
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        $query = Product::query()
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
            ->when($region !== null && $region !== '', fn ($query) => $query->where('region', $region))
            ->when($subRegion !== null && $subRegion !== '', fn ($query) => $query->where('sub_region', $subRegion))
            ->when($producer !== null && $producer !== '', fn ($query) => $query->where('producer', 'like', "%{$producer}%"))
            ->when($grape !== null && $grape !== '', fn ($query) => $query->where('grape', 'like', "%{$grape}%"))
            ->when($priceMin !== null, fn ($query) => $query->where('unit_price', '>=', $priceMin))
            ->when($priceMax !== null, fn ($query) => $query->where('unit_price', '<=', $priceMax))
            ->when($vintageMin !== null, fn ($query) => $query->where('vintage', '>=', $vintageMin))
            ->when($vintageMax !== null, fn ($query) => $query->where('vintage', '<=', $vintageMax));

        $query = $sort === self::DEFAULT_SORT
            ? $this->applyCellarOrder($query)
            : $query->orderBy($sort, $direction);

        return $query
            ->paginate($perPage)
            ->through(fn (Product $product) => $product->getData());
    }

    /**
     * Type (in WineColour display order, unknowns last) → country → region →
     * sub-region, empty geography sorting after named geography at each level,
     * with wine name as the final tie-break.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    private function applyCellarOrder($query)
    {
        $case = 'CASE colour';
        foreach (WineColour::cases() as $colour) {
            $case .= sprintf(" WHEN '%s' THEN %d", $colour->value, $colour->getSortOrder());
        }
        $case .= ' ELSE 99 END';

        return $query
            ->orderByRaw($case)
            ->orderByRaw("(country IS NULL OR country = '')")
            ->orderBy('country')
            ->orderByRaw("(region IS NULL OR region = '')")
            ->orderBy('region')
            ->orderByRaw("(sub_region IS NULL OR sub_region = '')")
            ->orderBy('sub_region')
            ->orderBy('wine_name');
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

    /**
     * Distinct, non-empty regions for filter dropdowns, optionally narrowed to
     * the currently-selected country so the list stays short and relevant.
     *
     * @param  array<int, int>|null  $supplierIds  restrict to these suppliers (null = all)
     * @return array<int, string>
     */
    public function regions(?array $supplierIds = null, ?string $country = null): array
    {
        return Product::query()
            ->whereNull('archived_at')
            ->when($supplierIds !== null, fn ($query) => $query->whereIn('supplier_id', $supplierIds))
            ->when($country !== null && $country !== '', fn ($query) => $query->where('country', $country))
            ->whereNotNull('region')
            ->where('region', '!=', '')
            ->distinct()
            ->orderBy('region')
            ->pluck('region')
            ->all();
    }

    /**
     * Distinct, non-empty sub-regions for filter dropdowns, optionally narrowed
     * to the selected country and/or region.
     *
     * @param  array<int, int>|null  $supplierIds  restrict to these suppliers (null = all)
     * @return array<int, string>
     */
    public function subRegions(?array $supplierIds = null, ?string $country = null, ?string $region = null): array
    {
        return Product::query()
            ->whereNull('archived_at')
            ->when($supplierIds !== null, fn ($query) => $query->whereIn('supplier_id', $supplierIds))
            ->when($country !== null && $country !== '', fn ($query) => $query->where('country', $country))
            ->when($region !== null && $region !== '', fn ($query) => $query->where('region', $region))
            ->whereNotNull('sub_region')
            ->where('sub_region', '!=', '')
            ->distinct()
            ->orderBy('sub_region')
            ->pluck('sub_region')
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
