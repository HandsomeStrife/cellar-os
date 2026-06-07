<?php

declare(strict_types=1);

use Domain\Catalogue\Data\ProductData;
use Domain\Catalogue\Enums\WineColour;
use Domain\Catalogue\Models\Product;
use Domain\Catalogue\Repositories\ProductRepository;
use Domain\Supplier\Models\Supplier;

it('returns DTOs, never models, from the repository', function () {
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create([
        'supplier_id' => $supplier->id,
        'colour' => WineColour::Red->value,
    ]);

    $result = (new ProductRepository)->findByUuid($product->uuid);

    expect($result)->toBeInstanceOf(ProductData::class)
        ->and($result->colour)->toBe(WineColour::Red)
        ->and($result->uuid)->toBe($product->uuid);
});

it('only returns geo-located products for the map', function () {
    $supplier = Supplier::factory()->create();
    Product::factory()->create(['supplier_id' => $supplier->id, 'latitude' => null, 'longitude' => null]);
    Product::factory()->create(['supplier_id' => $supplier->id, 'latitude' => '45.000000', 'longitude' => '2.000000']);

    $mapped = (new ProductRepository)->allForMap();

    expect($mapped)->toHaveCount(1)
        ->and($mapped->first())->toBeInstanceOf(ProductData::class);
});
