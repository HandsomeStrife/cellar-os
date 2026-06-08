<?php

declare(strict_types=1);

use App\Livewire\Orders\Index;
use App\Mail\PurchaseOrderMail;
use Domain\Billing\Enums\Plan;
use Domain\Catalogue\Models\Product;
use Domain\Inventory\Models\InventoryItem;
use Domain\Order\Enums\OrderStatus;
use Domain\Order\Models\Order;
use Domain\Order\Models\OrderItem;
use Domain\Supplier\Models\Supplier;
use Domain\User\Models\User;
use Domain\Venue\Models\Venue;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = userOnPlan(Plan::Starter);
    $this->supplier = Supplier::factory()->create(['email' => 'supplier@example.test']);
    $this->product = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'wine_name' => 'Test Wine',
        'unit_price' => '20.00',
    ]);
    $this->actingAs($this->user);
});

it('renders the orders page', function () {
    $this->get(route('orders'))->assertOk()->assertSeeLivewire(Index::class);
});

it('shows an upgrade gate for free users', function () {
    $this->actingAs(userOnPlan(Plan::Free));

    Livewire::test(Index::class)->assertSee('paid feature');
});

it('forbids creating orders for free users', function () {
    $this->actingAs(userOnPlan(Plan::Free));

    Livewire::test(Index::class)->call('openCreate')->assertForbidden();
});

it('creates an order with line items and computes the total', function () {
    Livewire::test(Index::class)
        ->call('openCreate')
        ->set('supplierId', $this->supplier->id)
        ->call('addLine', $this->product->id)
        ->call('setLineQty', 0, 12)
        ->call('createOrder')
        ->assertHasNoErrors();

    $order = Order::first();
    expect($order)->not->toBeNull()
        ->and((float) $order->total)->toBe(240.0);
    $this->assertDatabaseHas('order_items', [
        'order_id' => $order->id,
        'wine_name' => 'Test Wine',
        'quantity_units' => 12,
    ]);
});

it('requires a supplier and at least one line', function () {
    Livewire::test(Index::class)
        ->call('openCreate')
        ->call('createOrder')
        ->assertHasErrors(['supplierId', 'lines']);
});

it('prefills the create form from the catalogue basket', function () {
    session(['order-basket' => [$this->product->id => 6]]);

    $component = Livewire::test(Index::class)->call('openCreate');

    expect($component->get('lines'))->toHaveCount(1)
        ->and($component->get('lines')[0]['quantity'])->toBe(6)
        ->and($component->get('lines')[0]['wine_name'])->toBe('Test Wine');
});

it('receives a sent order into venue inventory', function () {
    $venue = Venue::factory()->create(['company_id' => $this->user->company_id]);
    $order = Order::factory()->create([
        'supplier_id' => $this->supplier->id,
        'venue_id' => $venue->id,
        'status' => OrderStatus::Sent->value,
    ]);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $this->product->id,
        'quantity_units' => 12,
        'unit_price_at_order' => '20.00',
    ]);

    Livewire::test(Index::class)->call('receive', $order->id);

    expect($order->fresh()->status)->toBe(OrderStatus::Received);
    $this->assertDatabaseHas('inventory_items', [
        'venue_id' => $venue->id,
        'product_id' => $this->product->id,
        'quantity_units' => 12,
    ]);
});

it('does not receive a sent order with no venue', function () {
    $order = Order::factory()->create([
        'supplier_id' => $this->supplier->id,
        'venue_id' => null,
        'status' => OrderStatus::Sent->value,
    ]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $this->product->id]);

    Livewire::test(Index::class)->call('receive', $order->id);

    expect($order->fresh()->status)->toBe(OrderStatus::Sent);
    $this->assertDatabaseCount('inventory_items', 0);
});

it('rejects receiving an order that is not Sent', function () {
    $venue = Venue::factory()->create(['company_id' => $this->user->company_id]);
    $order = Order::factory()->create([
        'supplier_id' => $this->supplier->id,
        'venue_id' => $venue->id,
        'status' => OrderStatus::Draft->value,
    ]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $this->product->id]);

    Livewire::test(Index::class)->call('receive', $order->id)->assertStatus(422);

    $this->assertDatabaseCount('inventory_items', 0);
});

it('does not double-receive (no inventory inflation)', function () {
    $venue = Venue::factory()->create(['company_id' => $this->user->company_id]);
    $order = Order::factory()->create([
        'supplier_id' => $this->supplier->id,
        'venue_id' => $venue->id,
        'status' => OrderStatus::Sent->value,
    ]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $this->product->id, 'quantity_units' => 12]);

    Livewire::test(Index::class)->call('receive', $order->id);
    // Second attempt is rejected because the order is now Received.
    Livewire::test(Index::class)->call('receive', $order->id)->assertStatus(422);

    $this->assertDatabaseHas('inventory_items', [
        'venue_id' => $venue->id,
        'product_id' => $this->product->id,
        'quantity_units' => 12,
    ]);
});

it('tops up an existing inventory line when receiving', function () {
    $venue = Venue::factory()->create(['company_id' => $this->user->company_id]);
    InventoryItem::factory()->create([
        'venue_id' => $venue->id,
        'product_id' => $this->product->id,
        'quantity_units' => 6,
    ]);
    $order = Order::factory()->create([
        'supplier_id' => $this->supplier->id,
        'venue_id' => $venue->id,
        'status' => OrderStatus::Sent->value,
    ]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $this->product->id, 'quantity_units' => 6]);

    Livewire::test(Index::class)->call('receive', $order->id);

    $this->assertDatabaseHas('inventory_items', [
        'venue_id' => $venue->id,
        'product_id' => $this->product->id,
        'quantity_units' => 12,
    ]);
});

it('forbids receiving into another user\'s venue', function () {
    $otherVenue = Venue::factory()->create(['company_id' => User::factory()->create()->company_id]);
    $order = Order::factory()->create([
        'supplier_id' => $this->supplier->id,
        'venue_id' => $otherVenue->id,
        'status' => OrderStatus::Sent->value,
    ]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $this->product->id]);

    Livewire::test(Index::class)->call('receive', $order->id)->assertForbidden();

    $this->assertDatabaseCount('inventory_items', 0);
});

it('updates an order status', function () {
    $order = Order::factory()->create(['supplier_id' => $this->supplier->id]);

    Livewire::test(Index::class)->call('setStatus', $order->id, OrderStatus::Sent->value);

    expect($order->fresh()->status)->toBe(OrderStatus::Sent);
});

it('deletes an order', function () {
    $order = Order::factory()->create(['supplier_id' => $this->supplier->id]);

    Livewire::test(Index::class)->set('viewingId', $order->id)->call('deleteOrder', $order->id);

    $this->assertDatabaseMissing('orders', ['id' => $order->id]);
});

it('downloads a purchase order PDF', function () {
    $order = Order::factory()->create(['supplier_id' => $this->supplier->id]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $this->product->id]);

    $response = $this->get(route('orders.pdf', $order->id));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('emails the order to the supplier and marks it sent', function () {
    Mail::fake();
    $order = Order::factory()->create(['supplier_id' => $this->supplier->id]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $this->product->id]);

    Livewire::test(Index::class)->call('sendEmail', $order->id);

    Mail::assertSent(PurchaseOrderMail::class);
    expect($order->fresh()->status)->toBe(OrderStatus::Sent);
});

it('does not email when the supplier has no address', function () {
    Mail::fake();
    $supplier = Supplier::factory()->create(['email' => null]);
    $order = Order::factory()->create(['supplier_id' => $supplier->id]);

    Livewire::test(Index::class)->call('sendEmail', $order->id);

    Mail::assertNothingSent();
});

it('blocks the PDF download for free users', function () {
    $order = Order::factory()->create(['supplier_id' => $this->supplier->id]);
    $this->actingAs(userOnPlan(Plan::Free));

    $this->get(route('orders.pdf', $order->id))->assertForbidden();
});

it('forbids line edits for free users', function () {
    $this->actingAs(userOnPlan(Plan::Free));

    Livewire::test(Index::class)->call('addLine', $this->product->id)->assertForbidden();
});

it('rejects attaching another user\'s venue', function () {
    $otherVenue = Venue::factory()->create(['company_id' => User::factory()->create()->company_id]);

    Livewire::test(Index::class)
        ->call('openCreate')
        ->set('supplierId', $this->supplier->id)
        ->set('venueId', $otherVenue->id)
        ->call('addLine', $this->product->id)
        ->call('createOrder')
        ->assertHasErrors('venueId');

    expect(Order::count())->toBe(0);
});

it('accepts the user\'s own venue', function () {
    $venue = Venue::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(Index::class)
        ->call('openCreate')
        ->set('supplierId', $this->supplier->id)
        ->set('venueId', $venue->id)
        ->call('addLine', $this->product->id)
        ->call('createOrder')
        ->assertHasNoErrors();

    expect(Order::first()->venue_id)->toBe($venue->id);
});

it('rejects an invalid status value', function () {
    $order = Order::factory()->create(['supplier_id' => $this->supplier->id]);

    Livewire::test(Index::class)->call('setStatus', $order->id, 'Bogus')->assertStatus(422);
});

it('rejects an order line with a non-existent product', function () {
    Livewire::test(Index::class)
        ->call('openCreate')
        ->set('supplierId', $this->supplier->id)
        ->set('lines', [['product_id' => 999999, 'wine_name' => 'Ghost', 'unit_price' => '10.00', 'quantity' => 1]])
        ->call('createOrder')
        ->assertHasErrors('lines.0.product_id');

    expect(Order::count())->toBe(0);
});
