<?php

declare(strict_types=1);

use Domain\Catalogue\Actions\BackfillCatalogueAttributesAction;
use Domain\Catalogue\Enums\WineColour;
use Domain\Catalogue\Models\Lwin;
use Domain\Catalogue\Models\Product;
use Domain\Supplier\Models\Supplier;

it('fills empty columns from the linked LWIN reference without overwriting supplier data', function () {
    $supplier = Supplier::factory()->create();
    Lwin::create([
        'lwin' => '1234567',
        'display_name' => 'Domaine Test Margaux',
        'producer_name' => 'Domaine Test',
        'wine' => 'Margaux',
        'country' => 'France',
        'region' => 'Bordeaux',
        'sub_region' => 'Margaux',
        'colour' => 'Red',
        'identity_key' => 'k', 'name_key' => 'n',
    ]);

    $product = Product::factory()->create([
        'supplier_id' => $supplier->id,
        'lwin' => '1234567',
        'country' => null,
        'region' => 'Margaux Estate',  // supplier's own value — must be kept
        'producer' => null,
        'colour' => null,
    ]);

    (new BackfillCatalogueAttributesAction)->execute();

    $fresh = $product->fresh();
    expect($fresh->country)->toBe('France')            // filled
        ->and($fresh->producer)->toBe('Domaine Test')  // filled
        ->and($fresh->colour)->toBe(WineColour::Red)   // filled
        ->and($fresh->region)->toBe('Margaux Estate'); // NOT overwritten
});

it('derives country from region when country is empty', function () {
    $supplier = Supplier::factory()->create();
    $burgundy = Product::factory()->create(['supplier_id' => $supplier->id, 'country' => null, 'region' => 'Bourgogne']);
    $barolo = Product::factory()->create(['supplier_id' => $supplier->id, 'country' => null, 'region' => 'Piemonte']);
    $unknown = Product::factory()->create(['supplier_id' => $supplier->id, 'country' => null, 'region' => 'Nowhere-on-Sea']);

    (new BackfillCatalogueAttributesAction)->execute();

    expect($burgundy->fresh()->country)->toBe('France')
        ->and($barolo->fresh()->country)->toBe('Italy')
        ->and($unknown->fresh()->country)->toBeNull();
});

it('treats a country name in the region field as the country', function () {
    $supplier = Supplier::factory()->create();
    $p = Product::factory()->create(['supplier_id' => $supplier->id, 'country' => null, 'region' => 'Germany']);

    (new BackfillCatalogueAttributesAction)->execute();

    expect($p->fresh()->country)->toBe('Germany');
});

it('geocodes wines that have a region but no coordinates', function () {
    $supplier = Supplier::factory()->create();
    $p = Product::factory()->create([
        'supplier_id' => $supplier->id, 'region' => 'Bordeaux', 'country' => 'France',
        'latitude' => null, 'longitude' => null,
    ]);

    (new BackfillCatalogueAttributesAction)->execute();

    expect($p->fresh()->latitude)->not->toBeNull()
        ->and($p->fresh()->longitude)->not->toBeNull();
});

it('skips archived wines', function () {
    $supplier = Supplier::factory()->create();
    $archived = Product::factory()->create(['supplier_id' => $supplier->id, 'country' => null, 'region' => 'Bourgogne', 'archived_at' => now()]);

    (new BackfillCatalogueAttributesAction)->execute();

    expect($archived->fresh()->country)->toBeNull();
});

it('dry run reports counts without writing', function () {
    $supplier = Supplier::factory()->create();
    Product::factory()->create(['supplier_id' => $supplier->id, 'country' => null, 'region' => 'Bourgogne']);

    $stats = (new BackfillCatalogueAttributesAction)->execute(apply: false);

    expect($stats['country'])->toBe(1)
        ->and(Product::whereNull('country')->count())->toBe(1);
});
