<?php

declare(strict_types=1);

use App\Livewire\Catalogue\Index;
use Domain\Catalogue\Enums\PriceState;
use Domain\Catalogue\Enums\SellingUnit;
use Domain\Catalogue\Models\Product;
use Domain\Catalogue\Repositories\ProductRepository;
use Domain\Supplier\Actions\ConnectCompanyToSupplierAction;
use Domain\Supplier\Models\Supplier;
use Domain\User\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->cheap = Supplier::factory()->create(['name' => 'Keen Prices Ltd']);
    $this->dear = Supplier::factory()->create(['name' => 'Fine & Rare']);
    $this->unconnected = Supplier::factory()->create(['name' => 'Strangers Wine Co']);

    foreach ([$this->cheap, $this->dear] as $supplier) {
        (new ConnectCompanyToSupplierAction)->execute($this->user->company_id, $supplier->id);
    }

    $this->listing = fn (Supplier $supplier, string $price) => Product::factory()->create([
        'supplier_id' => $supplier->id,
        'wine_name' => 'Chablis Premier Cru',
        'producer' => 'Domaine Laroche',
        'vintage' => 2021,
        'format_ml' => 750,
        'unit_price' => $price,
    ]);
});

it('opens the add panel with a quantity of one', function () {
    $product = ($this->listing)($this->dear, '30.00');

    Livewire::test(Index::class)
        ->call('openAdd', $product->id)
        ->assertSet('showAdd', true)
        ->assertSet('addingId', $product->id)
        ->assertSet('addQty', 1);
});

it('baskets the quantity the buyer chose', function () {
    $product = ($this->listing)($this->dear, '30.00');

    Livewire::test(Index::class)
        ->call('openAdd', $product->id)
        ->set('addQty', 7)
        ->call('confirmAdd')
        ->assertSet('basket', [$product->id => 7])
        // The panel closes and resets behind it.
        ->assertSet('showAdd', false)
        ->assertSet('addingId', null);
});

it('counts a case-sold quantity in cases but stores bottles', function () {
    $product = Product::factory()->create([
        'supplier_id' => $this->dear->id,
        'sold_by' => SellingUnit::Case,
        'case_size' => 12,
        'unit_price' => '10.00',
    ]);

    Livewire::test(Index::class)
        ->call('openAdd', $product->id)
        ->set('addQty', 3)
        ->call('confirmAdd')
        // 3 cases of 12.
        ->assertSet('basket', [$product->id => 36]);
});

it('refuses a quantity below one', function () {
    $product = ($this->listing)($this->dear, '30.00');

    Livewire::test(Index::class)
        ->call('openAdd', $product->id)
        ->set('addQty', 0)
        ->call('confirmAdd')
        ->assertSet('basket', [$product->id => 1]);
});

it('finds the same wine listed by another connected supplier, cheapest first', function () {
    $dearer = ($this->listing)($this->dear, '30.00');
    $cheaper = ($this->listing)($this->cheap, '24.00');

    $alternatives = (new ProductRepository)->alternativesFor(
        $dearer->getData(),
        [$this->cheap->id, $this->dear->id],
    );

    expect($alternatives->pluck('id')->all())->toBe([$cheaper->id]);
});

it('never shows pricing from a supplier the company is not connected to', function () {
    $mine = ($this->listing)($this->dear, '30.00');
    ($this->listing)($this->unconnected, '5.00');

    $alternatives = (new ProductRepository)->alternativesFor(
        $mine->getData(),
        [$this->cheap->id, $this->dear->id],
    );

    expect($alternatives)->toBeEmpty();
});

it('will not pass off a different wine as the same one', function () {
    $mine = ($this->listing)($this->dear, '30.00');

    // Same name and vintage, but a different grower — and a different bottle.
    Product::factory()->create([
        'supplier_id' => $this->cheap->id,
        'wine_name' => 'Chablis Premier Cru',
        'producer' => 'Someone Else Entirely',
        'vintage' => 2021,
        'format_ml' => 750,
        'unit_price' => '12.00',
    ]);
    Product::factory()->create([
        'supplier_id' => $this->cheap->id,
        'wine_name' => 'Chablis Premier Cru',
        'producer' => 'Domaine Laroche',
        'vintage' => 2019,
        'format_ml' => 750,
        'unit_price' => '12.00',
    ]);

    expect((new ProductRepository)->alternativesFor($mine->getData(), [$this->cheap->id, $this->dear->id]))
        ->toBeEmpty();
});

it('sorts a POA alternative after the priced ones', function () {
    $mine = ($this->listing)($this->dear, '30.00');
    $poa = ($this->listing)($this->cheap, '25.00');
    $poa->update(['unit_price' => null, 'price_state' => PriceState::Poa]);
    $priced = Product::factory()->create([
        'supplier_id' => $this->cheap->id,
        'wine_name' => 'Chablis Premier Cru',
        'producer' => 'Domaine Laroche',
        'vintage' => 2021,
        'format_ml' => 750,
        'unit_price' => '26.00',
    ]);

    $alternatives = (new ProductRepository)->alternativesFor(
        $mine->getData(),
        [$this->cheap->id, $this->dear->id],
    );

    expect($alternatives->pluck('id')->all())->toBe([$priced->id, $poa->id]);
});

it('switches the add panel to the cheaper supplier, keeping the quantity', function () {
    $dearer = ($this->listing)($this->dear, '30.00');
    $cheaper = ($this->listing)($this->cheap, '24.00');

    Livewire::test(Index::class)
        ->call('openAdd', $dearer->id)
        ->set('addQty', 6)
        ->call('switchTo', $cheaper->id)
        ->assertSet('addingId', $cheaper->id)
        ->assertSet('addQty', 6)
        ->call('confirmAdd')
        ->assertSet('basket', [$cheaper->id => 6]);
});

it('shows the saving against the other supplier in the add panel', function () {
    $dearer = ($this->listing)($this->dear, '30.00');
    ($this->listing)($this->cheap, '24.00');

    Livewire::test(Index::class)
        ->call('openAdd', $dearer->id)
        ->assertSee('Keen Prices Ltd')
        ->assertSee('a bottle cheaper');
});
