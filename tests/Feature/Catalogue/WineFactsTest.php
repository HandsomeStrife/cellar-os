<?php

declare(strict_types=1);

use App\Livewire\Catalogue\Index;
use Domain\Catalogue\Actions\ContributeWineFactsAction;
use Domain\Catalogue\Actions\UpsertProductAction;
use Domain\Catalogue\Data\ProductData;
use Domain\Catalogue\Models\Product;
use Domain\Catalogue\Models\WineFact;
use Domain\Catalogue\Support\WineIdentity;
use Domain\Supplier\Actions\ConnectCompanyToSupplierAction;
use Domain\Supplier\Models\Supplier;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

function productData(array $overrides = []): ProductData
{
    return ProductData::from(array_merge([
        'id' => null, 'uuid' => null, 'supplier_id' => null, 'raw_upload_id' => null,
        'wine_name' => 'Clos de Test', 'producer' => 'Domaine Verdier',
        'country' => null, 'region' => null, 'sub_region' => null,
        'grape' => null, 'colour' => null, 'vintage' => 2022,
        'format_ml' => 750, 'case_size' => 6, 'unit_price' => '15.00',
        'price_per_litre' => null, 'stock' => 0, 'latitude' => null, 'longitude' => null,
    ], $overrides));
}

// ---------------------------------------------------------------- identity ----

it('normalises wine identity across accents, case and punctuation', function () {
    expect(WineIdentity::keyFor('Château Palmer', 'Alter Égo'))
        ->toBe(WineIdentity::keyFor('chateau  PALMER,', 'Alter Ego!'))
        ->and(WineIdentity::keyFor('Château Palmer', 'Alter Égo'))->toBe('chateau palmer|alter ego');
});

it('refuses an identity without a producer (too ambiguous to share facts)', function () {
    expect(WineIdentity::keyFor(null, 'Chablis'))->toBeNull()
        ->and(WineIdentity::keyFor('  ', 'Chablis'))->toBeNull();
});

// -------------------------------------------------------------- contribute ----

it('contributes facts on product upsert with per-field provenance, never overwriting', function () {
    $a = Supplier::factory()->create();
    $b = Supplier::factory()->create();

    // Supplier A knows the origin but not the varietals.
    (new UpsertProductAction)->execute(productData(['supplier_id' => $a->id, 'country' => 'France', 'region' => 'Bordeaux']));

    $fact = WineFact::firstWhere('identity_key', 'domaine verdier|clos de test');
    expect($fact)->not->toBeNull()
        ->and($fact->country)->toBe('France')
        ->and($fact->grape)->toBeNull()
        ->and($fact->field_sources['country']['supplier_id'])->toBe($a->id);

    // Supplier B fills the varietals AND claims a different country — the
    // existing country must NOT be overwritten; the gap IS filled.
    (new UpsertProductAction)->execute(productData(['supplier_id' => $b->id, 'country' => 'Spain', 'grape' => ['Merlot', 'Cabernet Franc']]));

    $fact->refresh();
    expect($fact->country)->toBe('France')                       // kept
        ->and($fact->grape)->toBe(['Merlot', 'Cabernet Franc'])  // filled
        ->and($fact->field_sources['grape']['supplier_id'])->toBe($b->id)
        ->and($fact->observations)->toBe(2);
});

it('skips contribution for producer-less products', function () {
    (new ContributeWineFactsAction)->execute(productData(['producer' => null]));

    expect(WineFact::count())->toBe(0);
});

// -------------------------------------------------------------- enrichment ----

it('shows another vendor\'s varietals for a wine whose own supplier omits them, with the marker', function () {
    [$company, $user] = makeTenant();

    // Company's connected supplier A lists the wine WITHOUT varietals/colour.
    $a = Supplier::factory()->create();
    (new ConnectCompanyToSupplierAction)->execute($company->id, $a->id);
    (new UpsertProductAction)->execute(productData(['supplier_id' => $a->id, 'wine_name' => 'Vom Kalk', 'producer' => 'Markus Altenburger']));

    // Unrelated supplier B stocks the same wine WITH varietals (different company's world entirely).
    $b = Supplier::factory()->create();
    (new UpsertProductAction)->execute(productData(['supplier_id' => $b->id, 'wine_name' => 'Vom Kalk', 'producer' => 'Markus Altenburger', 'grape' => ['Chardonnay'], 'colour' => 'White', 'country' => 'Austria']));

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->assertSee('Vom Kalk')
        ->assertSee('Chardonnay')                                  // enriched varietal shown
        ->assertSee('Austria')                                     // enriched origin shown
        ->assertSee('Populated from another vendor', false);          // provenance note

    // The company's own product row is untouched in the DB — enrichment is display-only.
    $own = Product::where('supplier_id', $a->id)->first();
    expect($own->grape)->toBeNull()->and($own->country)->toBeNull();
});

it('never marks a product\'s own data as enriched', function () {
    [$company, $user] = makeTenant();
    $a = Supplier::factory()->create();
    (new ConnectCompanyToSupplierAction)->execute($company->id, $a->id);
    (new UpsertProductAction)->execute(productData(['supplier_id' => $a->id, 'grape' => ['Syrah'], 'country' => 'France', 'colour' => 'Red']));

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->assertSee('Syrah')
        ->assertDontSee('Populated from another vendor', false);
});

it('withholds a contested fact from enrichment (suppliers disagree on colour)', function () {
    [$company, $user] = makeTenant();
    $a = Supplier::factory()->create();
    (new ConnectCompanyToSupplierAction)->execute($company->id, $a->id);

    // Company's supplier lists the wine without colour.
    (new UpsertProductAction)->execute(productData(['supplier_id' => $a->id]));

    // Two other vendors disagree: White vs Red → colour becomes contested.
    (new UpsertProductAction)->execute(productData(['supplier_id' => Supplier::factory()->create()->id, 'colour' => 'White', 'grape' => ['Chardonnay']]));
    (new UpsertProductAction)->execute(productData(['supplier_id' => Supplier::factory()->create()->id, 'colour' => 'Red']));

    $fact = WineFact::firstWhere('identity_key', 'domaine verdier|clos de test');
    expect(array_keys($fact->field_conflicts))->toBe(['colour']);

    $this->actingAs($user);

    // Grape (uncontested) still enriches; colour (contested) is withheld.
    $own = Product::where('supplier_id', $a->id)->first();
    Livewire::test(Index::class)
        ->assertSee('Chardonnay')
        ->assertViewHas('enriched', fn ($enriched) => isset($enriched[$own->id]['grape']) && ! isset($enriched[$own->id]['colour']));
});

it('enriches a missing region even when the country is the product\'s own', function () {
    [$company, $user] = makeTenant();
    $a = Supplier::factory()->create();
    (new ConnectCompanyToSupplierAction)->execute($company->id, $a->id);
    (new UpsertProductAction)->execute(productData(['supplier_id' => $a->id, 'country' => 'France']));
    (new UpsertProductAction)->execute(productData(['supplier_id' => Supplier::factory()->create()->id, 'country' => 'France', 'region' => 'Jura']));

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->assertSee('Jura')
        ->assertSee('Populated from another vendor', false);
});

it('refuses placeholder producers as identities', function () {
    foreach (['N/A', 'Various', 'Unknown', 'TBC', '-'] as $placeholder) {
        expect(WineIdentity::keyFor($placeholder, 'Some Wine'))->toBeNull();
    }
});

it('survives a contribution against an existing identity without breaking the upsert', function () {
    // Pre-create the fact with the same identity — contribution becomes a
    // fill rather than an insert; the upsert must succeed regardless.
    WineFact::factory()->create(['identity_key' => 'domaine verdier|clos de test', 'wine_name' => 'Clos de Test', 'producer' => 'Domaine Verdier', 'country' => null, 'region' => null, 'grape' => null, 'colour' => null]);

    $product = (new UpsertProductAction)->execute(productData(['supplier_id' => Supplier::factory()->create()->id, 'grape' => ['Gamay']]));

    expect($product->id)->not->toBeNull()
        ->and(WineFact::firstWhere('identity_key', 'domaine verdier|clos de test')->grape)->toBe(['Gamay'])
        ->and(WineFact::count())->toBe(1);
});

it('never ships internal provenance to the client', function () {
    [$company, $user] = makeTenant();
    $a = Supplier::factory()->create(['name' => 'SECRET SOURCE SUPPLIER']);
    (new ConnectCompanyToSupplierAction)->execute($company->id, Supplier::factory()->create()->id);
    (new UpsertProductAction)->execute(productData(['supplier_id' => $a->id, 'grape' => ['Riesling']]));

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->assertDontSee('field_sources', false)
        ->assertDontSee('SECRET SOURCE SUPPLIER');
});

it('holds no pricing data in the facts store (schema guarantee)', function () {
    $columns = Schema::getColumnListing('wine_facts');

    expect(array_intersect($columns, ['unit_price', 'price', 'price_per_litre']))->toBe([]);
});
