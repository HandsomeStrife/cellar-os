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

    public function search(
        ?string $term = null,
        ?string $country = null,
        ?WineColour $colour = null,
        int $perPage = 24,
    ): LengthAwarePaginator {
        return Product::query()
            ->when($term !== null, fn ($query) => $query->where('wine_name', 'like', "%{$term}%"))
            ->when($country !== null, fn ($query) => $query->where('country', $country))
            ->when($colour !== null, fn ($query) => $query->where('colour', $colour->value))
            ->orderBy('wine_name')
            ->paginate($perPage)
            ->through(fn (Product $product) => $product->getData());
    }

    public function allForMap(): Collection
    {
        return Product::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->map(fn (Product $product) => $product->getData());
    }
}
