<?php

declare(strict_types=1);

use Domain\Admin\Models\Admin;
use Domain\Billing\Enums\Plan;
use Domain\Catalogue\Models\Product;
use Domain\Company\Models\Company;
use Domain\Inventory\Models\InventoryItem;
use Domain\Order\Models\Order;
use Domain\Supplier\Models\Supplier;
use Domain\User\Models\User;
use Domain\Venue\Models\Venue;
use Illuminate\Support\Facades\Hash;

it('seeds a coherent demo dataset', function () {
    $this->seed();

    // A default admin plus one demo user per plan tier.
    expect(Admin::where('email', 'admin@cellaros.test')->exists())->toBeTrue()
        ->and(Hash::check('password', Admin::first()->password))->toBeTrue();

    $tiers = [
        'free@cellaros.test' => Plan::Free,
        'starter@cellaros.test' => Plan::Starter,
        'demo@cellaros.test' => Plan::Pro,
        'group@cellaros.test' => Plan::Group,
    ];
    foreach ($tiers as $email => $plan) {
        $user = User::where('email', $email)->first();
        // The plan lives on the user's company now.
        expect(Company::find($user?->company_id)?->plan)->toBe($plan)
            ->and(Hash::check('password', $user->password))->toBeTrue();
    }

    // Shared catalogue + per-user venues/inventory/orders.
    expect(Supplier::count())->toBe(4)
        ->and(Product::count())->toBe(11)
        ->and(Product::whereNotNull('latitude')->count())->toBe(10)
        ->and(Venue::count())->toBe(5)
        ->and(InventoryItem::count())->toBe(10)
        ->and(Order::count())->toBe(7);

    // Every order's total matches the sum of its line items.
    Order::with('items')->get()->each(function ($order) {
        $expected = $order->items->sum(fn ($i) => $i->quantity_units * (float) $i->unit_price_at_order);
        expect((float) $order->total)->toBe($expected);
    });

    // UUIDs are populated (proves the HasUuid creating event still fires).
    expect(Product::first()->uuid)->not->toBeNull()
        ->and(Admin::first()->uuid)->not->toBeNull();
});

it('gives each journey the right shape of data', function () {
    $this->seed();

    $free = User::where('email', 'free@cellaros.test')->first();
    $group = User::where('email', 'group@cellaros.test')->first();

    // Free user: a venue, but no stock or orders yet (empty / getting-started state).
    $freeVenue = Venue::where('company_id', $free->company_id)->first();
    expect($freeVenue)->not->toBeNull()
        ->and(InventoryItem::where('venue_id', $freeVenue->id)->count())->toBe(0)
        ->and(Order::where('created_by', $free->id)->count())->toBe(0);

    // Group company: more than one venue, and a team (owner + member).
    expect(Venue::where('company_id', $group->company_id)->count())->toBe(2)
        ->and(User::where('company_id', $group->company_id)->count())->toBe(2);
});

it('is idempotent across repeated runs', function () {
    $this->seed();
    $this->seed();

    expect(Company::count())->toBe(4)
        ->and(User::count())->toBe(5)
        ->and(Admin::count())->toBe(1)
        ->and(Supplier::count())->toBe(4)
        ->and(Product::count())->toBe(11)
        ->and(Venue::count())->toBe(5)
        ->and(InventoryItem::count())->toBe(10)
        ->and(Order::count())->toBe(7);
});
