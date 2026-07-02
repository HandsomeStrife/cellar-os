<?php

declare(strict_types=1);

use App\Livewire\Catalogue\Index as CatalogueIndex;
use App\Livewire\Orders\Create as OrdersCreate;
use App\Livewire\Orders\Index as OrdersIndex;
use Domain\Billing\Enums\Plan;
use Domain\Catalogue\Models\Product;
use Domain\Order\Data\OrderItemData;
use Domain\Order\Enums\OrderStatus;
use Domain\Order\Models\Order;
use Domain\Order\Models\OrderItem;
use Domain\Supplier\Actions\ConnectCompanyToSupplierAction;
use Domain\Supplier\Models\Supplier;
use Domain\Venue\Models\Venue;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = userOnPlan(Plan::Starter);
    $this->supplier = Supplier::factory()->create();
    (new ConnectCompanyToSupplierAction)->execute($this->user->company_id, $this->supplier->id);
    $this->caseWine = Product::factory()->soldByCase(6, 120.0)->create([
        'supplier_id' => $this->supplier->id,
        'wine_name' => 'Case Red',
        'unit_price' => '20.00',
    ]);
    $this->actingAs($this->user);
});

it('baskets and steps a case-sold wine a case at a time (stored as bottles)', function () {
    Livewire::test(CatalogueIndex::class)
        ->call('addToBasket', $this->caseWine->id)
        ->assertSet('basket.'.$this->caseWine->id, 6)      // one case = 6 bottles
        ->call('addToBasket', $this->caseWine->id)
        ->assertSet('basket.'.$this->caseWine->id, 12)     // two cases
        ->call('setBasketCases', $this->caseWine->id, 1)
        ->assertSet('basket.'.$this->caseWine->id, 6)
        ->call('setBasketCases', $this->caseWine->id, 0)   // zero removes the line
        ->assertSet('basket.'.$this->caseWine->id, null);
});

it('snapshots the case framing onto the order when checking out the basket', function () {
    Livewire::test(CatalogueIndex::class)
        ->set('basket', [$this->caseWine->id => 12])       // two cases
        ->call('createOrders');

    $item = OrderItem::firstOrFail();
    expect($item->sold_by_at_order)->toBe('case')
        ->and($item->pack_size_at_order)->toBe(6)
        ->and((float) $item->pack_price_at_order)->toBe(120.0)
        ->and($item->quantity_units)->toBe(12)             // canonical: bottles
        ->and((float) $item->unit_price_at_order)->toBe(20.0)
        ->and((float) $item->order->total)->toBe(240.0);   // 12 × £20
});

it('receives a case order into inventory as bottles (no conversion)', function () {
    $venue = Venue::factory()->create(['company_id' => $this->user->company_id]);
    $order = Order::factory()->create([
        'company_id' => $this->user->company_id,
        'supplier_id' => $this->supplier->id,
        'venue_id' => $venue->id,
        'status' => OrderStatus::Sent->value,
    ]);
    OrderItem::factory()->soldByCase(6, 120.0)->create([
        'order_id' => $order->id,
        'product_id' => $this->caseWine->id,
        'quantity_units' => 12,                            // two cases = 12 bottles
        'unit_price_at_order' => '20.00',
    ]);

    Livewire::test(OrdersIndex::class)->call('receive', $order->id);

    expect($order->fresh()->status)->toBe(OrderStatus::Received);
    $this->assertDatabaseHas('inventory_items', [
        'venue_id' => $venue->id,
        'product_id' => $this->caseWine->id,
        'quantity_units' => 12,                            // bottles, not cases
    ]);
});

it('adds and snapshots a case line through the manual order create flow', function () {
    Livewire::test(OrdersCreate::class)
        ->set('supplierId', $this->supplier->id)
        ->call('addLine', $this->caseWine->id)             // +1 case = 6 bottles
        ->call('addLine', $this->caseWine->id)             // +1 case = 12 bottles
        ->assertSet('lines.0.quantity', 12)
        ->call('setLineCases', 0, 3)                       // 3 cases = 18 bottles
        ->assertSet('lines.0.quantity', 18)
        ->call('createOrder')
        ->assertHasNoErrors();

    $item = OrderItem::firstOrFail();
    expect($item->sold_by_at_order)->toBe('case')
        ->and($item->pack_size_at_order)->toBe(6)
        ->and($item->quantity_units)->toBe(18)
        ->and((float) $item->order->total)->toBe(360.0);   // 18 × £20
});

it('computes case framing on the order-item DTO', function () {
    $item = new OrderItemData(
        id: null, order_id: null, product_id: null,
        wine_name: 'Case Red',
        quantity_units: 14,                                // 2 cases of 6 + 2 loose
        unit_price_at_order: '20.00',
        currency_at_order: 'GBP',
        sold_by_at_order: 'case',
        pack_size_at_order: 6,
        pack_price_at_order: '120.00',
    );

    expect($item->soldByCaseAtOrder())->toBeTrue()
        ->and($item->casesAtOrder())->toBe(2)
        ->and($item->looseBottlesAtOrder())->toBe(2)
        ->and($item->casePriceAtOrder())->toBe('120.00')
        ->and($item->lineTotal())->toBe(280.0);            // 14 × £20

    $bottle = new OrderItemData(
        id: null, order_id: null, product_id: null,
        wine_name: 'Bottle White', quantity_units: 3,
        unit_price_at_order: '15.00', currency_at_order: 'GBP',
    );

    expect($bottle->soldByCaseAtOrder())->toBeFalse()
        ->and($bottle->casesAtOrder())->toBeNull()
        ->and($bottle->casePriceAtOrder())->toBeNull();
});
