<?php

declare(strict_types=1);

use Database\Seeders\DemoSeeder;
use Domain\Admin\Models\Admin;
use Domain\Billing\Enums\Plan;
use Domain\Catalogue\Enums\PriceState;
use Domain\Catalogue\Models\Product;
use Domain\Company\Models\Company;
use Domain\Inventory\Models\InventoryItem;
use Domain\Order\Models\Order;
use Domain\Supplier\Models\Supplier;
use Domain\Supplier\Models\SupplierUser;
use Domain\User\Models\User;
use Domain\Venue\Models\Venue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

it('seeds the clean default WITHOUT any supplier or catalogue data', function () {
    $this->seed();

    expect(Admin::where('email', 'admin@cellaros.test')->exists())->toBeTrue();

    foreach (['demo@cellaros.test' => Plan::Pro, 'group@cellaros.test' => Plan::Group] as $email => $plan) {
        $user = User::where('email', $email)->first();
        expect(Company::find($user?->company_id)?->plan)->toBe($plan)
            ->and(Hash::check('password', $user->password))->toBeTrue();
    }

    // PRODUCTION-SAFE: accounts only. Demo content is DemoSeeder's job.
    expect(Supplier::count())->toBe(0)
        ->and(Product::count())->toBe(0)
        ->and(Order::count())->toBe(0)
        ->and(InventoryItem::count())->toBe(0)
        ->and(SupplierUser::count())->toBe(0)
        ->and(Venue::count())->toBe(3);
});

it('builds a demo whose merchants are fictional and private to the demo companies', function () {
    $this->seed(DemoSeeder::class);

    $demoCompanyIds = Company::whereIn('name', DemoSeeder::COMPANIES)->pluck('id');

    $suppliers = Supplier::whereIn('name', DemoSeeder::SUPPLIERS)->get();
    expect($suppliers)->toHaveCount(count(DemoSeeder::SUPPLIERS));

    // Every demo merchant is PRIVATE to a demo company, so no other tenant can
    // see it and it can never appear in Discover.
    $suppliers->each(function (Supplier $supplier) use ($demoCompanyIds) {
        expect($demoCompanyIds->contains($supplier->created_by_company_id))->toBeTrue();
    });
});

it('arranges the demo so every headline feature has something to show', function () {
    $this->seed(DemoSeeder::class);

    $pro = User::where('email', 'demo@cellaros.test')->first();
    $supplierIds = DB::table('company_supplier')->where('company_id', $pro->company_id)->pluck('supplier_id');

    $wines = Product::whereIn('supplier_id', $supplierIds);

    expect((clone $wines)->count())->toBeGreaterThan(20)
        // A price the merchant withholds.
        ->and((clone $wines)->where('price_state', PriceState::Poa->value)->count())->toBeGreaterThanOrEqual(1)
        // Sparkling/fortified styles, so Type and Style separate.
        ->and((clone $wines)->whereNotNull('sub_type')->count())->toBeGreaterThanOrEqual(3)
        // Wines quoted by the case as well as the bottle.
        ->and((clone $wines)->where('sold_by', 'case')->count())->toBeGreaterThanOrEqual(1)
        // Orders across the lifecycle, including one to repeat.
        ->and(Order::where('company_id', $pro->company_id)->count())->toBeGreaterThanOrEqual(3)
        // Stock low enough for the dashboard alerts to fire.
        ->and(InventoryItem::where('quantity_units', '<=', 3)->count())->toBeGreaterThanOrEqual(1);

    // The same wine from two merchants, which is what price comparison needs.
    $duplicated = Product::whereIn('supplier_id', $supplierIds)
        ->selectRaw('wine_name, producer, vintage, format_ml, count(distinct supplier_id) as merchants')
        ->groupBy('wine_name', 'producer', 'vintage', 'format_ml')
        ->havingRaw('count(distinct supplier_id) > 1')
        ->get();

    expect($duplicated)->not->toBeEmpty();
});

it('resets the demo back to the same state', function () {
    $this->seed(DemoSeeder::class);

    $before = Product::whereIn('supplier_id', Supplier::whereIn('name', DemoSeeder::SUPPLIERS)->pluck('id'))->count();

    // Add some mess a demo would leave behind, then reset.
    Product::whereIn('supplier_id', Supplier::whereIn('name', DemoSeeder::SUPPLIERS)->pluck('id'))->limit(3)->delete();

    $this->artisan('demo:reset --force')->assertSuccessful();

    $after = Product::whereIn('supplier_id', Supplier::whereIn('name', DemoSeeder::SUPPLIERS)->pluck('id'))->count();

    expect($after)->toBe($before)
        ->and(User::where('email', 'demo@cellaros.test')->exists())->toBeTrue();
});

it('leaves other tenants alone when the demo is reset', function () {
    $this->seed(DemoSeeder::class);

    // A real company with its own private merchant and wine.
    $other = Company::factory()->create(['name' => 'Real Customer Ltd']);
    $theirSupplier = Supplier::factory()->create(['created_by_company_id' => $other->id]);
    $theirWine = Product::factory()->create(['supplier_id' => $theirSupplier->id]);

    $this->artisan('demo:reset --force')->assertSuccessful();

    expect(Company::find($other->id))->not->toBeNull()
        ->and(Supplier::find($theirSupplier->id))->not->toBeNull()
        ->and(Product::find($theirWine->id))->not->toBeNull();
});
