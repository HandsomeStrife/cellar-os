<?php

declare(strict_types=1);

use App\Livewire\Orders\Index;
use Domain\Billing\Enums\Plan;
use Domain\Catalogue\Enums\PriceState;
use Domain\Catalogue\Enums\SellingUnit;
use Domain\Catalogue\Models\Product;
use Domain\Order\Actions\RepeatOrderAction;
use Domain\Order\Enums\OrderStatus;
use Domain\Order\Models\Order;
use Domain\Supplier\Models\Supplier;
use Livewire\Livewire;

beforeEach(function () {
    [$this->company, $this->user, $this->venue] = makeTenant(Plan::Pro);
    $this->actingAs($this->user);
    $this->supplier = Supplier::factory()->create();

    $this->orderFor = function (array $lines): Order {
        $order = Order::factory()->create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'venue_id' => $this->venue->id,
            'created_by' => $this->user->id,
            'status' => OrderStatus::Sent,
        ]);

        foreach ($lines as [$product, $qty, $price]) {
            $order->items()->create([
                'product_id' => $product?->id,
                'wine_name' => $product?->wine_name ?? 'Gone Wine',
                'quantity_units' => $qty,
                'unit_price_at_order' => $price,
                'currency_at_order' => 'GBP',
                'sold_by_at_order' => SellingUnit::Bottle->value,
            ]);
        }

        return $order->fresh('items');
    };
});

it('raises a new draft with the same wines and quantities', function () {
    $wine = Product::factory()->create(['supplier_id' => $this->supplier->id, 'unit_price' => '20.00']);
    $order = ($this->orderFor)([[$wine, 12, '20.00']]);

    $result = (new RepeatOrderAction)->execute($order->id, $this->company->id, $this->user->id);

    expect($result['order']->status)->toBe(OrderStatus::Draft)
        ->and($result['order']->supplier_id)->toBe($this->supplier->id)
        ->and($result['order']->venue_id)->toBe($this->venue->id)
        ->and($result['order']->items)->toHaveCount(1)
        ->and($result['order']->items[0]->quantity_units)->toBe(12)
        ->and($result['changes'])->toBe([])
        // The original is untouched.
        ->and($order->fresh()->status)->toBe(OrderStatus::Sent);
});

it('prices the repeat from today\'s catalogue and reports the change', function () {
    $wine = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'wine_name' => 'Chablis',
        'unit_price' => '26.50',
    ]);
    // Ordered at the old price.
    $order = ($this->orderFor)([[$wine, 6, '20.00']]);

    $result = (new RepeatOrderAction)->execute($order->id, $this->company->id, $this->user->id);

    expect($result['order']->items[0]->unit_price_at_order)->toBe('26.50')
        ->and((float) $result['order']->total)->toBe(159.0)
        ->and($result['changes'])->toHaveCount(1)
        ->and($result['changes'][0]['wine_name'])->toBe('Chablis')
        ->and($result['changes'][0]['change'])->toContain('20.00 to 26.50');
});

it('says nothing about a price that has not moved', function () {
    $wine = Product::factory()->create(['supplier_id' => $this->supplier->id, 'unit_price' => '20.00']);
    $order = ($this->orderFor)([[$wine, 6, '20.00']]);

    expect((new RepeatOrderAction)->execute($order->id, $this->company->id)['changes'])->toBe([]);
});

it('flags a wine that has become POA and raises the line without a price', function () {
    $wine = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'wine_name' => 'Allocation Only',
        'unit_price' => null,
        'price_state' => PriceState::Poa,
    ]);
    $order = ($this->orderFor)([[$wine, 6, '20.00']]);

    $result = (new RepeatOrderAction)->execute($order->id, $this->company->id);

    expect($result['order']->items[0]->unit_price_at_order)->toBeNull()
        ->and($result['changes'][0]['change'])->toBe('now price on application')
        // A POA line contributes nothing to the total.
        ->and((float) $result['order']->total)->toBe(0.0);
});

it('keeps a delisted wine on the draft and flags it, rather than dropping it', function () {
    $archived = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'wine_name' => 'Discontinued Red',
        'unit_price' => '18.00',
        'archived_at' => now(),
    ]);
    $order = ($this->orderFor)([[$archived, 6, '18.00']]);

    $result = (new RepeatOrderAction)->execute($order->id, $this->company->id);

    expect($result['order']->items)->toHaveCount(1)
        ->and($result['order']->items[0]->wine_name)->toBe('Discontinued Red')
        ->and($result['changes'][0]['change'])->toBe('no longer listed by this supplier');
});

it('flags a wine that has left the catalogue entirely', function () {
    $wine = Product::factory()->create(['supplier_id' => $this->supplier->id, 'unit_price' => '18.00']);
    $order = ($this->orderFor)([[$wine, 6, '18.00']]);
    $wine->delete();

    $result = (new RepeatOrderAction)->execute($order->id, $this->company->id);

    expect($result['changes'][0]['change'])->toBe('no longer in your catalogue')
        ->and($result['order']->items)->toHaveCount(1);
});

it('refuses to repeat another company\'s order', function () {
    $wine = Product::factory()->create(['supplier_id' => $this->supplier->id, 'unit_price' => '18.00']);
    $order = ($this->orderFor)([[$wine, 6, '18.00']]);

    [$otherCompany] = makeTenant(Plan::Pro);

    expect(fn () => (new RepeatOrderAction)->execute($order->id, $otherCompany->id))
        ->toThrow(RuntimeException::class);
});

it('repeats from the orders screen and lands the buyer on the new draft', function () {
    $wine = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'wine_name' => 'Chablis',
        'unit_price' => '30.00',
    ]);
    $order = ($this->orderFor)([[$wine, 6, '20.00']]);

    Livewire::test(Index::class)
        ->call('repeat', $order->id)
        ->assertDispatched('toast')
        // The new draft is opened, with the differences on show.
        ->assertSet('viewingId', fn (?int $id) => $id !== null && $id !== $order->id)
        ->assertSee('changed since the order you repeated')
        ->assertSee('20.00 to 30.00');

    expect(Order::count())->toBe(2);
});
