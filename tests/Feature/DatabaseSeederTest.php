<?php

declare(strict_types=1);

use Database\Seeders\DemoSupplierSeeder;
use Domain\Admin\Models\Admin;
use Domain\Billing\Enums\Plan;
use Domain\Catalogue\Models\Product;
use Domain\Company\Models\Company;
use Domain\Inventory\Models\InventoryItem;
use Domain\Order\Models\Order;
use Domain\Supplier\Models\ParsedWine;
use Domain\Supplier\Models\Supplier;
use Domain\Supplier\Models\SupplierUser;
use Domain\User\Models\User;
use Domain\Venue\Models\Venue;
use Illuminate\Support\Facades\Hash;

// ------------------------------------------------- clean default seeder ----

it('seeds the clean default WITHOUT any dummy supplier data', function () {
    $this->seed();

    // Admin + one demo company per plan tier.
    expect(Admin::where('email', 'admin@cellaros.test')->exists())->toBeTrue();

    $tiers = [
        'free@cellaros.test' => Plan::Free,
        'starter@cellaros.test' => Plan::Starter,
        'demo@cellaros.test' => Plan::Pro,
        'group@cellaros.test' => Plan::Group,
    ];
    foreach ($tiers as $email => $plan) {
        $user = User::where('email', $email)->first();
        expect(Company::find($user?->company_id)?->plan)->toBe($plan)
            ->and(Hash::check('password', $user->password))->toBeTrue();
    }

    // PRODUCTION-SAFE: no fictional suppliers, wines, orders, or portal users.
    expect(Supplier::count())->toBe(0)
        ->and(Product::count())->toBe(0)
        ->and(Order::count())->toBe(0)
        ->and(InventoryItem::count())->toBe(0)
        ->and(SupplierUser::count())->toBe(0)
        ->and(Venue::count())->toBe(5);
});

it('wires demo journeys to REAL suppliers when a catalogue exists', function () {
    // Simulate a golden-imported state: two public suppliers with priced wines.
    $farr = Supplier::factory()->create(['name' => 'Real Fine Wines']);
    $flint = Supplier::factory()->create(['name' => 'Real Merchants']);
    Product::factory()->count(4)->create(['supplier_id' => $farr->id, 'unit_price' => '25.00']);
    Product::factory()->count(3)->create(['supplier_id' => $flint->id, 'unit_price' => '40.00']);

    $this->seed();

    $demo = User::where('email', 'demo@cellaros.test')->first();

    // The Pro demo company is connected to real suppliers with orders + stock.
    expect(DB::table('company_supplier')->where('company_id', $demo->company_id)->count())->toBeGreaterThanOrEqual(2)
        ->and(Order::where('company_id', $demo->company_id)->count())->toBeGreaterThanOrEqual(1)
        ->and(InventoryItem::count())->toBeGreaterThanOrEqual(1);

    // Order totals always match their lines.
    Order::with('items')->get()->each(function ($order) {
        $expected = $order->items->sum(fn ($i) => $i->quantity_units * (float) $i->unit_price_at_order);
        expect((float) $order->total)->toBe($expected);
    });

    // Idempotent.
    $this->seed();
    expect(Company::count())->toBe(4)->and(User::count())->toBe(5);
});

it('keeps the free demo account in its empty getting-started state', function () {
    $this->seed();

    $free = User::where('email', 'free@cellaros.test')->first();
    $freeVenue = Venue::where('company_id', $free->company_id)->first();

    expect($freeVenue)->not->toBeNull()
        ->and(InventoryItem::where('venue_id', $freeVenue->id)->count())->toBe(0)
        ->and(Order::where('created_by', $free->id)->count())->toBe(0)
        ->and(DB::table('company_supplier')->where('company_id', $free->company_id)->count())->toBe(0);
});

// ----------------------------------------------- opt-in demo content ----

it('seeds the full fictional demo via DemoSupplierSeeder (dev/E2E only)', function () {
    $this->seed(DemoSupplierSeeder::class);

    expect(Supplier::count())->toBe(4)            // 3 fictional shared + Borough private
        ->and(Product::count())->toBe(11)
        ->and(Product::whereNotNull('latitude')->count())->toBe(10)
        ->and(Venue::count())->toBe(5)
        ->and(InventoryItem::count())->toBe(10)
        ->and(Order::count())->toBe(7)
        ->and(SupplierUser::count())->toBe(4)
        ->and(ParsedWine::count())->toBe(3);

    // Group company shape: two venues, owner + venue-scoped member.
    $group = User::where('email', 'group@cellaros.test')->first();
    expect(Venue::where('company_id', $group->company_id)->count())->toBe(2)
        ->and(User::where('company_id', $group->company_id)->count())->toBe(2);

    // Idempotent.
    $this->seed(DemoSupplierSeeder::class);
    expect(Supplier::count())->toBe(4)->and(Product::count())->toBe(11)->and(Order::count())->toBe(7);
});
