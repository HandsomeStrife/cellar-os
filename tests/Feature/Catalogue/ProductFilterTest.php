<?php

declare(strict_types=1);

use Domain\Catalogue\Enums\WineColour;
use Domain\Catalogue\Models\Product;
use Domain\Catalogue\Repositories\ProductRepository;
use Domain\Supplier\Models\Supplier;

beforeEach(function () {
    $this->supplier = Supplier::factory()->create();
});

function makeWine(int $supplierId, array $overrides = []): Product
{
    return Product::factory()->create(array_merge([
        'supplier_id' => $supplierId,
    ], $overrides));
}

it('finds a white Burgundy under £20 with the new filters combined', function () {
    $target = makeWine($this->supplier->id, [
        'wine_name' => 'Petit Chablis',
        'colour' => WineColour::White,
        'region' => 'Burgundy',
        'unit_price' => '18.50',
    ]);

    // Decoys that each fail exactly one of the three filters.
    makeWine($this->supplier->id, ['wine_name' => 'Red Burgundy', 'colour' => WineColour::Red, 'region' => 'Burgundy', 'unit_price' => '15.00']);
    makeWine($this->supplier->id, ['wine_name' => 'White Bordeaux', 'colour' => WineColour::White, 'region' => 'Bordeaux', 'unit_price' => '12.00']);
    makeWine($this->supplier->id, ['wine_name' => 'Grand Cru Chablis', 'colour' => WineColour::White, 'region' => 'Burgundy', 'unit_price' => '85.00']);

    $results = (new ProductRepository)->search(
        colour: WineColour::White,
        region: 'Burgundy',
        priceMax: 20.0,
    );

    expect($results->pluck('id')->all())->toBe([$target->id]);
});

it('filters by sub-region, producer, grape and vintage range', function () {
    $target = makeWine($this->supplier->id, [
        'wine_name' => 'Côte de Nuits Villages',
        'region' => 'Burgundy',
        'sub_region' => 'Côte de Nuits',
        'producer' => 'Domaine Dujac',
        'grape' => ['Pinot Noir'],
        'vintage' => 2018,
        'unit_price' => '45.00',
    ]);

    makeWine($this->supplier->id, ['sub_region' => 'Côte de Beaune', 'producer' => 'Domaine Dujac', 'grape' => ['Pinot Noir'], 'vintage' => 2018]);
    makeWine($this->supplier->id, ['sub_region' => 'Côte de Nuits', 'producer' => 'Another Estate', 'grape' => ['Pinot Noir'], 'vintage' => 2018]);
    makeWine($this->supplier->id, ['sub_region' => 'Côte de Nuits', 'producer' => 'Domaine Dujac', 'grape' => ['Chardonnay'], 'vintage' => 2018]);
    makeWine($this->supplier->id, ['sub_region' => 'Côte de Nuits', 'producer' => 'Domaine Dujac', 'grape' => ['Pinot Noir'], 'vintage' => 2005]);

    expect((new ProductRepository)->search(subRegion: 'Côte de Nuits')->pluck('id')->all())->toContain($target->id)
        ->and((new ProductRepository)->search(producer: 'Dujac', grape: 'Pinot Noir', vintageMin: 2015, vintageMax: 2020, subRegion: 'Côte de Nuits')->pluck('id')->all())->toBe([$target->id]);
});

it('exposes distinct regions and sub-regions cascaded by the broader selection', function () {
    makeWine($this->supplier->id, ['country' => 'France', 'region' => 'Burgundy', 'sub_region' => 'Côte de Nuits']);
    makeWine($this->supplier->id, ['country' => 'France', 'region' => 'Bordeaux', 'sub_region' => 'Médoc']);
    makeWine($this->supplier->id, ['country' => 'Italy', 'region' => 'Tuscany', 'sub_region' => 'Chianti']);

    $repo = new ProductRepository;

    expect($repo->regions())->toBe(['Bordeaux', 'Burgundy', 'Tuscany'])
        ->and($repo->regions(country: 'France'))->toBe(['Bordeaux', 'Burgundy'])
        ->and($repo->subRegions(country: 'France', region: 'Burgundy'))->toBe(['Côte de Nuits']);
});
