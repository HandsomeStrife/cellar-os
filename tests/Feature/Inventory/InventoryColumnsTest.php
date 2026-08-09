<?php

declare(strict_types=1);

use App\Livewire\Inventory\Index;
use Domain\Billing\Enums\Plan;
use Domain\Catalogue\Enums\WineType;
use Domain\Catalogue\Models\Product;
use Domain\Catalogue\Models\WineFact;
use Domain\Catalogue\Support\WineIdentity;
use Domain\Inventory\Models\InventoryItem;
use Domain\Supplier\Actions\ConnectCompanyToSupplierAction;
use Domain\Supplier\Models\Supplier;
use Domain\Venue\Actions\SyncUserVenuesAction;
use Domain\Venue\Models\Venue;
use Livewire\Livewire;

beforeEach(function () {
    [$this->company, $this->user, $this->venue] = makeTenant(Plan::Pro);
    $this->actingAs($this->user);

    $this->supplier = Supplier::factory()->create(['name' => 'Berry Merchants']);
    (new ConnectCompanyToSupplierAction)->execute($this->company->id, $this->supplier->id);

    $this->product = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'wine_name' => 'Chablis Premier Cru',
        'producer' => 'Domaine Laroche',
        'country' => 'France',
        'region' => 'Bourgogne',
        'colour' => WineType::White,
        'vintage' => 2021,
        'format_ml' => 750,
    ]);

    InventoryItem::factory()->create([
        'venue_id' => $this->venue->id,
        'product_id' => $this->product->id,
        'quantity_units' => 24,
    ]);
});

it('shows the wine\'s full information, not just its name', function () {
    Livewire::test(Index::class)
        ->assertSee('Chablis Premier Cru')
        ->assertSee('Domaine Laroche')
        ->assertSee('Berry Merchants')
        ->assertSee('France')
        ->assertSee('Bourgogne')
        ->assertSee('White')
        ->assertSee('2021');
})->group('inventory');

it('hides a column the user has switched off', function () {
    Livewire::test(Index::class)
        ->set('visibleColumns', ['producer', 'colour'])
        ->assertSee('Domaine Laroche')
        // Supplier and region are off, so their values are gone from the table.
        ->assertDontSee('Berry Merchants')
        ->assertDontSee('Bourgogne');
});

it('keeps the column choice to known columns, in a fixed order', function () {
    Livewire::test(Index::class)
        ->set('visibleColumns', ['files', 'nonsense', 'producer'])
        ->assertSet('visibleColumns', ['producer', 'files']);
});

it('gap-fills a missing attribute from the shared facts store, marked as enriched', function () {
    $bare = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'wine_name' => 'Barolo Riserva',
        'producer' => 'Giacomo Conterno',
        'country' => null,
        'colour' => null,
    ]);
    InventoryItem::factory()->create(['venue_id' => $this->venue->id, 'product_id' => $bare->id]);

    WineFact::create([
        'identity_key' => WineIdentity::keyFor('Giacomo Conterno', 'Barolo Riserva'),
        'name_key' => WineIdentity::normalise('Barolo Riserva'),
        'producer' => 'Giacomo Conterno',
        'wine_name' => 'Barolo Riserva',
        'country' => 'Italy',
        'colour' => WineType::Red,
    ]);

    Livewire::test(Index::class)
        ->assertSee('Italy')
        // The provenance marker the catalogue uses travels with the value.
        ->assertSee('another vendor');
});

it('searches on producer as well as wine name', function () {
    $other = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'wine_name' => 'Barolo Riserva',
        'producer' => 'Giacomo Conterno',
    ]);
    InventoryItem::factory()->create(['venue_id' => $this->venue->id, 'product_id' => $other->id]);

    Livewire::test(Index::class)
        ->set('search', 'Conterno')
        ->assertSee('Barolo Riserva')
        ->assertDontSee('Chablis Premier Cru');
});

it('scopes the columns to the venue the user is on', function () {
    // A second venue with its own stock the user can also reach.
    $other = Venue::factory()->create(['company_id' => $this->company->id]);
    (new SyncUserVenuesAction)->execute($this->user->id, [$this->venue->id, $other->id]);

    Livewire::test(Index::class)
        ->set('venueId', $other->id)
        ->assertDontSee('Chablis Premier Cru');
});
