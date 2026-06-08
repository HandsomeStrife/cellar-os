<?php

declare(strict_types=1);

use App\Livewire\Dashboard;
use App\Livewire\Orders\Index;
use Domain\Billing\Enums\Plan;
use Domain\Catalogue\Models\Product;
use Domain\Order\Enums\OrderStatus;
use Domain\Order\Models\Order;
use Domain\Order\Models\OrderItem;
use Domain\Supplier\Models\Supplier;
use Livewire\Livewire;

/**
 * The whole point of the company-as-tenant refactor: one company must never be
 * able to read, mutate, delete, email or receive another company's orders.
 */
beforeEach(function () {
    $this->supplier = Supplier::factory()->create(['email' => 'supplier@example.test']);
    $this->product = Product::factory()->create(['supplier_id' => $this->supplier->id, 'wine_name' => 'Tenant Wine']);

    // Two separate tenants, each with an order.
    [$this->companyA, $this->ownerA, $this->venueA] = makeTenant(Plan::Pro);
    [$this->companyB, $this->ownerB, $this->venueB] = makeTenant(Plan::Pro);

    $this->orderB = Order::factory()->create([
        'company_id' => $this->companyB->id,
        'supplier_id' => $this->supplier->id,
        'venue_id' => $this->venueB->id,
        'status' => OrderStatus::Sent->value,
        'notes' => 'COMPANY B SECRET',
    ]);
    OrderItem::factory()->create([
        'order_id' => $this->orderB->id,
        'product_id' => $this->product->id,
        'quantity_units' => 5,
        'unit_price_at_order' => '20.00',
    ]);

    $this->actingAs($this->ownerA);
});

it('does not list another company\'s orders', function () {
    Livewire::test(Index::class)->assertDontSee('COMPANY B SECRET');
});

it('does not let you view another company\'s order by id', function () {
    Livewire::test(Index::class)
        ->set('viewingId', $this->orderB->id)
        ->assertDontSee('COMPANY B SECRET');
});

it('forbids changing another company\'s order status', function () {
    Livewire::test(Index::class)
        ->call('setStatus', $this->orderB->id, OrderStatus::Received->value)
        ->assertForbidden();

    expect($this->orderB->fresh()->status)->toBe(OrderStatus::Sent);
});

it('forbids deleting another company\'s order', function () {
    Livewire::test(Index::class)
        ->call('deleteOrder', $this->orderB->id)
        ->assertForbidden();

    $this->assertDatabaseHas('orders', ['id' => $this->orderB->id]);
});

it('forbids emailing another company\'s order', function () {
    Livewire::test(Index::class)
        ->call('sendEmail', $this->orderB->id)
        ->assertForbidden();
});

it('forbids receiving another company\'s order into your inventory', function () {
    Livewire::test(Index::class)
        ->call('receive', $this->orderB->id)
        ->assertForbidden();

    $this->assertDatabaseCount('inventory_items', 0);
});

it('forbids downloading another company\'s order PDF', function () {
    // The download route 404s a non-owned order (doesn't reveal its existence).
    $this->get(route('orders.pdf', $this->orderB->id))->assertNotFound();
});

it('scopes dashboard order counts to your own company', function () {
    // Company A has no orders; B has one. A's dashboard must show zero.
    $this->get(route('dashboard'))->assertOk()->assertDontSee('COMPANY B SECRET');

    Livewire::test(Dashboard::class)
        ->assertViewHas('orderCount', 0);
});
