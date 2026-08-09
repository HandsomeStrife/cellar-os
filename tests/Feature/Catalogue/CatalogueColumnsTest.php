<?php

declare(strict_types=1);

use App\Livewire\Catalogue\Index;
use Domain\Catalogue\Models\Product;
use Domain\Catalogue\Repositories\ProductRepository;
use Domain\Supplier\Actions\ConnectCompanyToSupplierAction;
use Domain\Supplier\Models\Supplier;
use Domain\User\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->berry = Supplier::factory()->create(['name' => 'Berry Merchants']);
    $this->zephyr = Supplier::factory()->create(['name' => 'Zephyr Wines']);

    foreach ([$this->berry, $this->zephyr] as $supplier) {
        (new ConnectCompanyToSupplierAction)->execute($this->user->company_id, $supplier->id);
    }
});

it('shows producer and supplier as their own columns', function () {
    Product::factory()->create([
        'supplier_id' => $this->berry->id,
        'wine_name' => 'Chablis Premier Cru',
        'producer' => 'Domaine Laroche',
    ]);

    Livewire::test(Index::class)
        ->assertSee('Producer')
        ->assertSee('Supplier')
        ->assertSee('Domaine Laroche')
        ->assertSee('Berry Merchants');
});

it('drops the supplier column and its toggle once one supplier is selected', function () {
    Product::factory()->create(['supplier_id' => $this->berry->id, 'wine_name' => 'Chablis']);
    Product::factory()->create(['supplier_id' => $this->zephyr->id, 'wine_name' => 'Barolo']);

    $all = Livewire::test(Index::class);
    $all->assertViewHas('showSupplierColumn', true)
        ->assertViewHas('columns', fn (array $columns) => array_key_exists('supplier', $columns));

    $one = Livewire::test(Index::class)->set('supplierFilter', (string) $this->berry->id);
    $one->assertViewHas('showSupplierColumn', false)
        ->assertViewHas('columns', fn (array $columns) => ! array_key_exists('supplier', $columns));
});

it('sorts by the supplier name, not the supplier id', function () {
    // Zephyr is created second, so id order and name order disagree.
    $barolo = Product::factory()->create(['supplier_id' => $this->zephyr->id, 'wine_name' => 'Barolo']);
    $chablis = Product::factory()->create(['supplier_id' => $this->berry->id, 'wine_name' => 'Chablis']);

    $ascending = (new ProductRepository)->search(sort: 'supplier', direction: 'asc')->pluck('id')->all();

    expect($ascending)->toBe([$chablis->id, $barolo->id]);
});
