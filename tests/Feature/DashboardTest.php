<?php

declare(strict_types=1);

use App\Livewire\Dashboard;
use Domain\Catalogue\Models\Product;
use Domain\Inventory\Models\InventoryItem;
use Domain\Supplier\Models\Supplier;
use Domain\User\Models\User;
use Domain\Venue\Models\Venue;
use Livewire\Livewire;

it('renders inventory analytics for the user\'s venues', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->create(['company_id' => $user->company_id]);
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create([
        'supplier_id' => $supplier->id,
        'wine_name' => 'Analytics Red',
        'country' => 'France',
    ]);
    InventoryItem::factory()->create([
        'venue_id' => $venue->id,
        'product_id' => $product->id,
        'quantity_units' => 5,           // < 12 → low stock
        'last_purchase_price' => '20.00', // value 100
    ]);

    $this->actingAs($user);

    Livewire::test(Dashboard::class)
        ->assertSee('In-stock value')
        ->assertSee('Cellar composition')
        ->assertSee('By country')
        ->assertSee('France')
        ->assertSee('Needs attention')
        ->assertSee('Analytics Red');
});

it('shows the getting-started guide when there is no stock', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Dashboard::class)->assertSee('Set up your cellar');
});
