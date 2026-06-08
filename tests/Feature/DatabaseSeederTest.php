<?php

declare(strict_types=1);

use Domain\Admin\Models\Admin;
use Domain\Billing\Enums\Plan;
use Domain\Catalogue\Models\Product;
use Domain\Inventory\Models\InventoryItem;
use Domain\Order\Models\Order;
use Domain\Order\Models\OrderItem;
use Domain\Supplier\Models\Supplier;
use Domain\User\Models\User;

it('seeds a coherent demo dataset', function () {
    $this->seed();

    expect(User::where('email', 'demo@cellaros.test')->where('plan', Plan::Pro->value)->exists())->toBeTrue()
        ->and(Admin::where('email', 'admin@cellaros.test')->exists())->toBeTrue()
        ->and(Supplier::count())->toBe(3)
        ->and(Product::count())->toBe(10)
        ->and(Product::whereNotNull('latitude')->count())->toBe(10)
        ->and(InventoryItem::count())->toBe(3)
        ->and(Order::count())->toBe(1);

    // The sample order's total matches its line items.
    $order = Order::with('items')->first();
    $expected = $order->items->sum(fn ($i) => $i->quantity_units * (float) $i->unit_price_at_order);
    expect((float) $order->total)->toBe($expected);

    // UUIDs are populated (proves the HasUuid creating event still fires).
    expect(Product::first()->uuid)->not->toBeNull()
        ->and(Admin::first()->uuid)->not->toBeNull();
});

it('is idempotent across repeated runs', function () {
    $this->seed();
    $this->seed();

    expect(User::where('email', 'demo@cellaros.test')->count())->toBe(1)
        ->and(User::where('email', 'demo@cellaros.test')->first()->plan)->toBe(Plan::Pro)
        ->and(Admin::count())->toBe(1)
        ->and(Supplier::count())->toBe(3)
        ->and(Product::count())->toBe(10)
        ->and(InventoryItem::count())->toBe(3)
        ->and(Order::count())->toBe(1)
        ->and(OrderItem::count())->toBe(2);
});
