<?php

declare(strict_types=1);

use Domain\Catalogue\Actions\ArchiveUnseenProductsAction;
use Domain\Catalogue\Actions\UpsertProductAction;
use Domain\Catalogue\Data\ProductData;
use Domain\Catalogue\Models\Product;
use Domain\Catalogue\Repositories\ProductRepository;
use Domain\Supplier\Models\Supplier;
use Domain\Supplier\Models\SupplierDocument;

function wineData(int $supplierId, string $name, array $overrides = []): ProductData
{
    return ProductData::from(array_merge([
        'id' => null,
        'uuid' => null,
        'supplier_id' => $supplierId,
        'raw_upload_id' => null,
        'wine_name' => $name,
        'producer' => 'Test Producer',
        'country' => 'France',
        'region' => null,
        'sub_region' => null,
        'grape' => null,
        'colour' => null,
        'vintage' => 2020,
        'format_ml' => 750,
        'case_size' => 6,
        'unit_price' => '12.00',
        'price_per_litre' => null,
        'stock' => 0,
        'latitude' => null,
        'longitude' => null,
    ], $overrides));
}

it('stamps last_seen_at and document provenance on upsert', function () {
    $supplier = Supplier::factory()->create();
    $document = SupplierDocument::factory()->create(['supplier_id' => $supplier->id]);

    $data = (new UpsertProductAction)->execute(wineData($supplier->id, 'Chablis'), sourceDocumentId: $document->id);

    $product = Product::find($data->id);
    expect($product->last_seen_at)->not->toBeNull()
        ->and($product->archived_at)->toBeNull()
        ->and($product->source_document_id)->toBe($document->id);
});

it('un-archives a wine that reappears in a later edition', function () {
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create([
        'supplier_id' => $supplier->id,
        'wine_name' => 'Chablis',
        'vintage' => 2020,
        'format_ml' => 750,
        'archived_at' => now()->subWeek(),
    ]);

    (new UpsertProductAction)->execute(wineData($supplier->id, 'Chablis'));

    expect($product->fresh()->archived_at)->toBeNull();
});

it('archives only the wines left pointing at a superseded document', function () {
    $supplier = Supplier::factory()->create();
    $oldDoc = SupplierDocument::factory()->create(['supplier_id' => $supplier->id]);
    $newDoc = SupplierDocument::factory()->create(['supplier_id' => $supplier->id]);

    $dropped = Product::factory()->create(['supplier_id' => $supplier->id, 'source_document_id' => $oldDoc->id]);
    $stillListed = Product::factory()->create(['supplier_id' => $supplier->id, 'source_document_id' => $newDoc->id]);
    $unrelated = Product::factory()->create(['supplier_id' => $supplier->id, 'source_document_id' => null]);

    $count = (new ArchiveUnseenProductsAction)->execute($oldDoc->id);

    expect($count)->toBe(1)
        ->and($dropped->fresh()->archived_at)->not->toBeNull()
        ->and($stillListed->fresh()->archived_at)->toBeNull()
        ->and($unrelated->fresh()->archived_at)->toBeNull();
});

it('hides archived wines from browse, search, map and counts but not direct lookup', function () {
    $supplier = Supplier::factory()->create();
    $active = Product::factory()->create([
        'supplier_id' => $supplier->id,
        'wine_name' => 'Active Chablis',
        'latitude' => '47.000000',
        'longitude' => '3.500000',
    ]);
    $archived = Product::factory()->create([
        'supplier_id' => $supplier->id,
        'wine_name' => 'Archived Chablis',
        'latitude' => '47.000000',
        'longitude' => '3.500000',
        'archived_at' => now(),
    ]);

    $repo = new ProductRepository;

    expect($repo->search(term: 'Chablis')->pluck('id')->all())->toBe([$active->id])
        ->and($repo->paginate()->pluck('id')->all())->toBe([$active->id])
        ->and($repo->count())->toBe(1)
        ->and($repo->allForMap()->pluck('id')->all())->toBe([$active->id])
        // Direct lookups keep working so inventory/order references render.
        ->and($repo->findByUuid($archived->uuid))->not->toBeNull()
        ->and($repo->findMany([$archived->id]))->toHaveCount(1);
});
