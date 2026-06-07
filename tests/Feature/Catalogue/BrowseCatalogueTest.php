<?php

declare(strict_types=1);

use App\Livewire\Catalogue\Index;
use Domain\Catalogue\Enums\WineColour;
use Domain\Catalogue\Models\Product;
use Domain\Supplier\Models\Supplier;
use Domain\User\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->supplier = Supplier::factory()->create();
});

it('renders the catalogue page', function () {
    $this->get(route('catalogue'))
        ->assertOk()
        ->assertSeeLivewire(Index::class);
});

it('lists and searches products', function () {
    Product::factory()->create(['supplier_id' => $this->supplier->id, 'wine_name' => 'Chablis Premier Cru']);
    Product::factory()->create(['supplier_id' => $this->supplier->id, 'wine_name' => 'Barolo Riserva']);

    Livewire::test(Index::class)
        ->assertSee('Chablis Premier Cru')
        ->assertSee('Barolo Riserva')
        ->set('search', 'Chablis')
        ->assertSee('Chablis Premier Cru')
        ->assertDontSee('Barolo Riserva');
});

it('filters by colour', function () {
    Product::factory()->create(['supplier_id' => $this->supplier->id, 'wine_name' => 'Crimson One', 'colour' => WineColour::Red->value]);
    Product::factory()->create(['supplier_id' => $this->supplier->id, 'wine_name' => 'Pale One', 'colour' => WineColour::White->value]);

    Livewire::test(Index::class)
        ->set('colour', WineColour::Red->value)
        ->assertSee('Crimson One')
        ->assertDontSee('Pale One');
});

it('toggles sort direction on a column', function () {
    Livewire::test(Index::class)
        ->assertSet('sort', 'wine_name')
        ->assertSet('direction', 'asc')
        ->call('sortBy', 'unit_price')
        ->assertSet('sort', 'unit_price')
        ->assertSet('direction', 'asc')
        ->call('sortBy', 'unit_price')
        ->assertSet('direction', 'desc');
});

it('edits a price inline and recomputes price per litre', function () {
    $product = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'unit_price' => '10.00',
        'format_ml' => 750,
    ]);

    Livewire::test(Index::class)
        ->call('startEditPrice', $product->id, '10.00')
        ->set('priceInput', '25.50')
        ->call('savePrice')
        ->assertHasNoErrors()
        ->assertSet('editingPriceId', null);

    expect((float) $product->fresh()->unit_price)->toBe(25.5)
        ->and(round((float) $product->fresh()->price_per_litre, 2))->toBe(34.0);
});

it('validates the inline price', function () {
    $product = Product::factory()->create(['supplier_id' => $this->supplier->id]);

    Livewire::test(Index::class)
        ->call('startEditPrice', $product->id, '10')
        ->set('priceInput', 'not-a-number')
        ->call('savePrice')
        ->assertHasErrors(['priceInput' => 'numeric']);
});

it('manages the order basket', function () {
    $product = Product::factory()->create(['supplier_id' => $this->supplier->id, 'unit_price' => '20.00']);

    Livewire::test(Index::class)
        ->call('addToBasket', $product->id)
        ->assertSet('basket', [$product->id => 1])
        ->call('setBasketQty', $product->id, 3)
        ->assertSet('basket', [$product->id => 3])
        ->assertSee('£60.00')
        ->call('removeFromBasket', $product->id)
        ->assertSet('basket', []);
});
