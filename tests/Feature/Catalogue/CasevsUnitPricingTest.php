<?php

declare(strict_types=1);

use App\Livewire\Catalogue\Index;
use Domain\Catalogue\Actions\ImportCatalogueWinesAction;
use Domain\Catalogue\Actions\UpsertProductAction;
use Domain\Catalogue\Data\ProductData;
use Domain\Catalogue\Enums\SellingUnit;
use Domain\Catalogue\Models\Product;
use Domain\Supplier\Actions\ConnectCompanyToSupplierAction;
use Domain\Supplier\Models\Supplier;
use Domain\User\Models\User;
use Livewire\Livewire;

it('derives the case price from the per-bottle price and case size when no pack price is set', function () {
    $product = Product::factory()->soldByCase(6)->create([
        'unit_price' => '20.00',
        'pack_price' => null,
    ])->getData();

    expect($product->soldByCase())->toBeTrue()
        ->and($product->displayPrice())->toBe('120.00')          // 20 × 6
        ->and($product->perBottleEquivalent())->toBe('20.00');
});

it('prefers the exact quoted pack price over the derived one', function () {
    $product = Product::factory()->soldByCase(6, 115.0)->create([
        'unit_price' => '20.00',
    ])->getData();

    expect($product->displayPrice())->toBe('115.00')             // exact quote wins
        ->and($product->perBottleEquivalent())->toBe('20.00');
});

it('shows the per-bottle price with no equivalent line for bottle-sold wines', function () {
    $product = Product::factory()->create([
        'sold_by' => 'bottle',
        'unit_price' => '18.50',
    ])->getData();

    expect($product->soldByCase())->toBeFalse()
        ->and($product->displayPrice())->toBe('18.50')
        ->and($product->perBottleEquivalent())->toBeNull();
});

it('persists the selling unit and pack price through the idempotent upsert', function () {
    $supplier = Supplier::factory()->create();

    $data = ProductData::from([
        'id' => null,
        'uuid' => null,
        'supplier_id' => $supplier->id,
        'raw_upload_id' => null,
        'wine_name' => 'Alabaster',
        'producer' => 'Teso La Monja',
        'country' => 'Spain',
        'region' => 'Toro',
        'sub_region' => null,
        'grape' => ['Tinta de Toro'],
        'colour' => 'Red',
        'vintage' => 2019,
        'format_ml' => 750,
        'case_size' => 6,
        'sold_by' => 'case',
        'unit_price' => '212.50',
        'pack_price' => '1275.00',
        'price_per_litre' => null,
        'stock' => 0,
        'latitude' => null,
        'longitude' => null,
    ]);

    (new UpsertProductAction)->execute($data);

    $row = Product::where('wine_name', 'Alabaster')->firstOrFail();

    expect($row->sold_by)->toBe(SellingUnit::Case)
        ->and((float) $row->pack_price)->toBe(1275.0)
        ->and((float) $row->unit_price)->toBe(212.5);
});

it('round-trips the selling unit through a golden snapshot restore', function () {
    $supplier = Supplier::factory()->create();
    $publicNameMap = [$supplier->name => $supplier->id];

    $rows = [[
        'supplier' => $supplier->name,
        'wine_name' => 'Case Wine',
        'vintage' => 2020,
        'format_ml' => 750,
        'case_size' => 12,
        'sold_by' => 'case',
        'unit_price' => '10.00',
        'pack_price' => '118.00',
    ]];

    (new ImportCatalogueWinesAction)->execute($rows, $publicNameMap);

    $row = Product::where('wine_name', 'Case Wine')->firstOrFail();

    expect($row->sold_by)->toBe(SellingUnit::Case)
        ->and((float) $row->pack_price)->toBe(118.0)
        ->and($row->case_size)->toBe(12);
});

it('renders the case price with a /case suffix and a per-bottle equivalent in the catalogue', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $supplier = Supplier::factory()->create();
    (new ConnectCompanyToSupplierAction)->execute($user->company_id, $supplier->id);

    Product::factory()->soldByCase(6, 1275.0)->create([
        'supplier_id' => $supplier->id,
        'wine_name' => 'Alabaster Teso La Monja',
        'unit_price' => '212.50',
    ]);

    Livewire::test(Index::class)
        ->assertSee('Alabaster Teso La Monja')
        ->assertSee('/case')
        ->assertSee('/ btl');
});
