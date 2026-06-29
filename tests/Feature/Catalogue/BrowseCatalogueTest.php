<?php

declare(strict_types=1);

use App\Livewire\Catalogue\Index;
use Domain\Billing\Enums\Plan;
use Domain\Catalogue\Enums\WineColour;
use Domain\Catalogue\Models\Product;
use Domain\Catalogue\Repositories\ProductRepository;
use Domain\Company\Models\Company;
use Domain\Order\Models\Order;
use Domain\Supplier\Actions\ConnectCompanyToSupplierAction;
use Domain\Supplier\Models\Supplier;
use Domain\User\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->supplier = Supplier::factory()->create();
    // The catalogue shows wines from connected suppliers only.
    (new ConnectCompanyToSupplierAction)->execute($this->user->company_id, $this->supplier->id);
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

it('only shows wines from the company\'s connected suppliers', function () {
    $stranger = Supplier::factory()->create();
    Product::factory()->create(['supplier_id' => $this->supplier->id, 'wine_name' => 'Connected Wine']);
    Product::factory()->create(['supplier_id' => $stranger->id, 'wine_name' => 'Hidden Wine']);

    Livewire::test(Index::class)
        ->assertSee('Connected Wine')
        ->assertDontSee('Hidden Wine');
});

it('shows a connect-suppliers prompt when the company has no connections', function () {
    $loner = userOnPlan(Plan::Starter); // fresh company, no connections
    $this->actingAs($loner);
    Product::factory()->create(['supplier_id' => $this->supplier->id, 'wine_name' => 'Somewhere Wine']);

    Livewire::test(Index::class)
        ->assertSee('No suppliers connected yet')
        ->assertDontSee('Somewhere Wine');
});

it('forbids deleting or repricing a wine from a supplier you do not own', function () {
    $product = Product::factory()->create(['supplier_id' => $this->supplier->id]); // connected, but public

    Livewire::test(Index::class)->call('deleteProduct', $product->id)->assertForbidden();
    $this->assertDatabaseHas('products', ['id' => $product->id]);

    Livewire::test(Index::class)
        ->call('startEditPrice', $product->id, '10.00')
        ->set('priceInput', '999')
        ->call('savePrice')
        ->assertForbidden();
});

it('lets you delete a wine from your own private supplier', function () {
    $private = Supplier::factory()->create(['created_by_company_id' => $this->user->company_id]);
    (new ConnectCompanyToSupplierAction)->execute($this->user->company_id, $private->id);
    $product = Product::factory()->create(['supplier_id' => $private->id]);

    Livewire::test(Index::class)->call('deleteProduct', $product->id)->assertHasNoErrors();
    $this->assertDatabaseMissing('products', ['id' => $product->id]);
});

it('ignores basket adds for unconnected suppliers', function () {
    $stranger = Supplier::factory()->create();
    $product = Product::factory()->create(['supplier_id' => $stranger->id]);

    Livewire::test(Index::class)
        ->call('addToBasket', $product->id)
        ->assertSet('basket', []);
});

it('excludes private suppliers\' wines from the global sourcing map', function () {
    $private = Supplier::factory()->create(['created_by_company_id' => Company::factory()->create()->id]);
    Product::factory()->create(['supplier_id' => $this->supplier->id, 'wine_name' => 'Public Wine', 'latitude' => '1.0', 'longitude' => '1.0']);
    Product::factory()->create(['supplier_id' => $private->id, 'wine_name' => 'Private Wine', 'latitude' => '2.0', 'longitude' => '2.0']);

    $names = (new ProductRepository)->allForMap()->pluck('wine_name')->all();

    expect($names)->toContain('Public Wine')->not->toContain('Private Wine');
});

it('filters by colour', function () {
    Product::factory()->create(['supplier_id' => $this->supplier->id, 'wine_name' => 'Crimson One', 'colour' => WineColour::Red->value]);
    Product::factory()->create(['supplier_id' => $this->supplier->id, 'wine_name' => 'Pale One', 'colour' => WineColour::White->value]);

    Livewire::test(Index::class)
        ->set('colour', WineColour::Red->value)
        ->assertSee('Crimson One')
        ->assertDontSee('Pale One');
});

it('filters by region and price range and clears them', function () {
    Product::factory()->create(['supplier_id' => $this->supplier->id, 'wine_name' => 'Cheap White Burgundy', 'colour' => WineColour::White->value, 'region' => 'Burgundy', 'unit_price' => '18.00']);
    Product::factory()->create(['supplier_id' => $this->supplier->id, 'wine_name' => 'Grand Cru Burgundy', 'colour' => WineColour::White->value, 'region' => 'Burgundy', 'unit_price' => '120.00']);
    Product::factory()->create(['supplier_id' => $this->supplier->id, 'wine_name' => 'Loire White', 'colour' => WineColour::White->value, 'region' => 'Loire', 'unit_price' => '14.00']);

    Livewire::test(Index::class)
        ->set('region', 'Burgundy')
        ->set('priceMax', '20')
        ->assertSee('Cheap White Burgundy')
        ->assertDontSee('Grand Cru Burgundy')
        ->assertDontSee('Loire White')
        ->call('resetFilters')
        ->assertSet('region', '')
        ->assertSet('priceMax', '')
        ->assertSee('Grand Cru Burgundy')
        ->assertSee('Loire White');
});

it('cascades: changing country clears the now-stale region and sub-region', function () {
    Livewire::test(Index::class)
        ->set('region', 'Burgundy')
        ->set('sub_region', 'Côte de Nuits')
        ->set('country', 'Italy')
        ->assertSet('region', '')
        ->assertSet('sub_region', '');
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
    // Inline edit is allowed only for the company's own private suppliers.
    $private = Supplier::factory()->create(['created_by_company_id' => $this->user->company_id]);
    (new ConnectCompanyToSupplierAction)->execute($this->user->company_id, $private->id);
    $product = Product::factory()->create([
        'supplier_id' => $private->id,
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

it('deletes a product from the catalogue', function () {
    $private = Supplier::factory()->create(['created_by_company_id' => $this->user->company_id]);
    (new ConnectCompanyToSupplierAction)->execute($this->user->company_id, $private->id);
    $product = Product::factory()->create(['supplier_id' => $private->id]);

    Livewire::test(Index::class)->call('deleteProduct', $product->id);

    $this->assertDatabaseMissing('products', ['id' => $product->id]);
});

it('creates one draft order per supplier from the basket', function () {
    $user = userOnPlan(Plan::Starter);
    $this->actingAs($user);
    $supplierTwo = Supplier::factory()->create();
    (new ConnectCompanyToSupplierAction)->execute($user->company_id, $this->supplier->id);
    (new ConnectCompanyToSupplierAction)->execute($user->company_id, $supplierTwo->id);
    $p1 = Product::factory()->create(['supplier_id' => $this->supplier->id, 'unit_price' => '10.00']);
    $p2 = Product::factory()->create(['supplier_id' => $supplierTwo->id, 'unit_price' => '20.00']);

    Livewire::test(Index::class)
        ->call('addToBasket', $p1->id)
        ->call('addToBasket', $p2->id)
        ->call('createOrders')
        ->assertRedirect(route('orders'));

    expect(Order::count())->toBe(2);
    $this->assertDatabaseHas('orders', ['supplier_id' => $this->supplier->id]);
    $this->assertDatabaseHas('orders', ['supplier_id' => $supplierTwo->id]);
});

it('forbids basket checkout for free users', function () {
    $product = Product::factory()->create(['supplier_id' => $this->supplier->id]);

    Livewire::test(Index::class)
        ->call('addToBasket', $product->id)
        ->call('createOrders')
        ->assertForbidden();

    expect(Order::count())->toBe(0);
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
