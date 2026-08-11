<?php

declare(strict_types=1);

use Database\Seeders\DemoSeeder;
use Domain\Admin\Models\Admin;
use Domain\Billing\Enums\Plan;
use Domain\Catalogue\Actions\UpsertProductAction;
use Domain\Catalogue\Data\ProductData;
use Domain\Catalogue\Enums\PriceState;
use Domain\Catalogue\Models\Product;
use Domain\Catalogue\Models\WineFact;
use Domain\Company\Models\Company;
use Domain\Inventory\Models\InventoryItem;
use Domain\Order\Models\Order;
use Domain\Supplier\Models\Supplier;
use Domain\Supplier\Models\SupplierUser;
use Domain\User\Models\User;
use Domain\Venue\Models\Venue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

it('connects the trade-reference account to every live supplier, and nothing private', function () {
    // The catalogue this account is meant to show: a public supplier with a
    // wine in it. Seeded BEFORE the demo, because that is the real order —
    // suppliers are parsed long before anyone rebuilds the demo.
    $listed = Supplier::factory()->create(['name' => 'Real Listed Merchant', 'created_by_company_id' => null]);
    Product::factory()->create(['supplier_id' => $listed->id, 'archived_at' => null]);

    // A public supplier whose wines have all been archived, and another
    // tenant's private merchant. Neither belongs in the list.
    $emptied = Supplier::factory()->create(['name' => 'Delisted Merchant', 'created_by_company_id' => null]);
    Product::factory()->create(['supplier_id' => $emptied->id, 'archived_at' => now()]);

    $other = Company::factory()->create(['name' => 'Real Customer Ltd']);
    $theirs = Supplier::factory()->create(['created_by_company_id' => $other->id]);
    Product::factory()->create(['supplier_id' => $theirs->id, 'archived_at' => null]);

    $this->seed(DemoSeeder::class);

    $trade = User::where('email', 'trade@cellaros.test')->first();
    expect(Company::find($trade?->company_id)?->plan)->toBe(Plan::Group);

    $connected = DB::table('company_supplier')->where('company_id', $trade->company_id)->pluck('supplier_id');

    expect($connected)->toContain($listed->id)
        ->and($connected)->not->toContain($emptied->id)
        ->and($connected)->not->toContain($theirs->id);

    // It owns no merchants of its own, so it can't edit a price or delete a
    // wine: those are private-supplier-only. Safe to hand out.
    expect(Supplier::where('created_by_company_id', $trade->company_id)->count())->toBe(0)
        ->and($connected->intersect(Supplier::whereIn('name', DemoSeeder::SUPPLIERS)->pluck('id'))->count())->toBe(0);

    // And it is torn down with the rest of the demo, connections included.
    $this->artisan('demo:reset --force')->assertSuccessful();

    expect(Supplier::find($listed->id))->not->toBeNull()
        ->and(DB::table('company_supplier')->whereIn('company_id', [$trade->company_id])->count())->toBe(0);
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

it('resets cleanly after a demo where people actually clicked things', function () {
    // The point of the command is running it AFTER a demo, so it has to
    // survive the debris one leaves: a raw upload, a cost-ledger entry, a
    // basket-driven order, parsed rows.
    $this->seed(DemoSeeder::class);

    $supplierId = Supplier::whereIn('name', DemoSeeder::SUPPLIERS)->value('id');
    $userId = User::where('email', 'demo@cellaros.test')->value('id');

    DB::table('raw_uploads')->insert([
        'uuid' => (string) Str::uuid(),
        'supplier_id' => $supplierId,
        'uploaded_by' => $userId,
        'file_name' => 'demo.csv',
        'file_type' => 'text/csv',
        'rows' => json_encode([['Wine' => 'X']]),
        'status' => 'pending',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('llm_calls')->insert([
        'uuid' => (string) Str::uuid(),
        'purpose' => 'ai_search',
        'model' => 'claude-haiku-4-5',
        'input_tokens' => 100, 'output_tokens' => 50, 'cost_usd' => '0.000100',
        'supplier_id' => $supplierId,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('demo:reset --force')->assertSuccessful();

    expect(User::where('email', 'demo@cellaros.test')->exists())->toBeTrue()
        // The cost ledger survives the demo being rebuilt — spend history is
        // ours, not the demo's.
        ->and(DB::table('llm_calls')->count())->toBe(1);
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

it('never lets invented demo wines teach the shared facts store', function () {
    // wine_facts is cross-supplier knowledge, shown to real buyers as "another
    // vendor's information" and exported to golden — so a fabricated producer
    // must never enter it.
    $this->seed(DemoSeeder::class);

    $inventedProducers = ['Maison Perrelet', 'Domaine des Ormes', 'Cantina Vecchia Corte', 'Quinta do Ribeiro'];

    expect(WineFact::whereIn('producer', $inventedProducers)->count())->toBe(0);

    // …while a real import still does contribute.
    $supplier = Supplier::factory()->create();
    (new UpsertProductAction)->execute(ProductData::from([
        'id' => null, 'uuid' => null, 'supplier_id' => $supplier->id, 'raw_upload_id' => null,
        'wine_name' => 'Real Chablis', 'producer' => 'Real Domaine', 'country' => 'France',
        'region' => null, 'sub_region' => null, 'grape' => ['Chardonnay'], 'colour' => null,
        'vintage' => 2022, 'format_ml' => 750, 'case_size' => 6, 'unit_price' => '20.00',
        'price_per_litre' => null, 'stock' => 0, 'latitude' => null, 'longitude' => null,
    ]));

    expect(WineFact::where('producer', 'Real Domaine')->exists())->toBeTrue();
});
