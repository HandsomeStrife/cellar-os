<?php

declare(strict_types=1);

use App\Livewire\Map\Index;
use Domain\Catalogue\Models\Product;
use Domain\Supplier\Models\Supplier;
use Domain\User\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->supplier = Supplier::factory()->create();
});

it('renders the map page', function () {
    $this->get(route('map'))->assertOk()->assertSeeLivewire(Index::class);
});

it('shows an empty state when there are no geo-located wines', function () {
    Product::factory()->create(['supplier_id' => $this->supplier->id, 'latitude' => null, 'longitude' => null]);

    Livewire::test(Index::class)->assertSee('Nothing to map yet');
});

it('lists countries for geo-located wines', function () {
    Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'country' => 'France',
        'latitude' => '48.85',
        'longitude' => '2.35',
    ]);
    Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'country' => 'Italy',
        'latitude' => '45.07',
        'longitude' => '7.69',
    ]);

    Livewire::test(Index::class)
        ->assertSee('By country')
        ->assertSee('France')
        ->assertSee('Italy')
        ->assertSee('2 geo-located wines');
});

it('does not emit raw markup from a wine name into the map payload', function () {
    Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'wine_name' => '<img src=x onerror=alert(1)>',
        'latitude' => '48.85',
        'longitude' => '2.35',
    ]);

    $html = Livewire::test(Index::class)->html();

    // @js JSON-encodes + hex-escapes angle brackets, so no literal tag appears.
    expect($html)->not->toContain('<img src=x');
});
