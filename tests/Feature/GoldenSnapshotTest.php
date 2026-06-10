<?php

declare(strict_types=1);

use Domain\Catalogue\Models\Product;
use Domain\Catalogue\Models\WineFact;
use Domain\Company\Models\Company;
use Domain\Supplier\Models\Supplier;
use Domain\Supplier\Models\SupplierParseProfile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function seedCanonical(): array
{
    $public = Supplier::factory()->create(['name' => 'Golden Imports', 'onboarded_at' => now()]);
    $private = Supplier::factory()->create(['name' => 'Private Cellar', 'created_by_company_id' => Company::factory()->create()->id]);

    $wine = Product::factory()->create([
        'supplier_id' => $public->id, 'wine_name' => 'Golden Chablis', 'producer' => 'Domaine Or',
        'grape' => ['Chardonnay'], 'colour' => 'White', 'country' => 'France', 'region' => 'Burgundy',
        'vintage' => 2022, 'format_ml' => 750, 'unit_price' => '19.00',
    ]);
    Product::factory()->create(['supplier_id' => $private->id, 'wine_name' => 'Secret Wine']);

    SupplierParseProfile::factory()->create([
        'supplier_id' => $public->id, 'company_id' => null, 'mode' => 'document',
        'recipe' => ['strategy' => 'pattern', 'rules' => ['row_regex' => 'x']],
    ]);
    // A company-scoped profile (tenant data — must NOT export).
    SupplierParseProfile::factory()->create(['supplier_id' => $public->id, 'company_id' => Company::factory()->create()->id]);

    WineFact::factory()->create([
        'identity_key' => 'domaine or|golden chablis', 'wine_name' => 'Golden Chablis', 'producer' => 'Domaine Or',
        'grape' => ['Chardonnay'], 'colour' => 'White',
        'field_sources' => ['grape' => ['supplier_id' => $public->id, 'observed_at' => '2026-06-10T00:00:00+00:00']],
        'field_conflicts' => ['colour' => 1],
        'observations' => 3,
    ]);

    return [$public, $private, $wine];
}

it('round-trips canonical data through export, wipe, and import', function () {
    [$public] = seedCanonical();

    $this->artisan('wine:export-golden')->assertSuccessful();

    // Simulate a full reset of canonical tables.
    WineFact::query()->delete();
    Product::query()->delete();
    SupplierParseProfile::query()->delete();
    Supplier::query()->delete();

    $this->artisan('wine:import-golden')->assertSuccessful();

    // Supplier restored as public + onboarded.
    $restored = Supplier::where('name', 'Golden Imports')->first();
    expect($restored)->not->toBeNull()
        ->and($restored->created_by_company_id)->toBeNull()
        ->and($restored->onboarded_at)->not->toBeNull();

    // Wine restored with attributes.
    $wine = Product::where('wine_name', 'Golden Chablis')->first();
    expect($wine->supplier_id)->toBe($restored->id)
        ->and($wine->grape)->toBe(['Chardonnay'])
        ->and($wine->unit_price)->toBe('19.00');

    // Fact restored EXACTLY — provenance, conflicts, observations.
    $fact = WineFact::firstWhere('identity_key', 'domaine or|golden chablis');
    expect($fact->field_conflicts)->toBe(['colour' => 1])
        ->and($fact->observations)->toBe(3)
        ->and($fact->field_sources['grape']['observed_at'])->toBe('2026-06-10T00:00:00+00:00');

    // The learned recipe survived (no re-study needed).
    $profile = SupplierParseProfile::where('supplier_id', $restored->id)->whereNull('company_id')->first();
    expect($profile->recipe['strategy'])->toBe('pattern');
});

it('excludes tenant data from the golden export', function () {
    seedCanonical();

    $this->artisan('wine:export-golden')->assertSuccessful();

    $suppliers = json_decode(Storage::disk('local')->get('golden/suppliers.json'), true);
    $wines = json_decode(Storage::disk('local')->get('golden/wines.json'), true);
    $profiles = json_decode(Storage::disk('local')->get('golden/parse-profiles.json'), true);

    expect(array_column($suppliers, 'name'))->toBe(['Golden Imports'])      // private supplier excluded
        ->and(array_column($wines, 'wine_name'))->not->toContain('Secret Wine')
        ->and($profiles)->toHaveCount(1);                                   // company-scoped profile excluded
});

it('imports idempotently (double import duplicates nothing)', function () {
    seedCanonical();
    $this->artisan('wine:export-golden')->assertSuccessful();

    $this->artisan('wine:import-golden')->assertSuccessful();
    $this->artisan('wine:import-golden')->assertSuccessful();

    expect(Supplier::where('name', 'Golden Imports')->count())->toBe(1)
        ->and(Product::where('wine_name', 'Golden Chablis')->count())->toBe(1)
        ->and(WineFact::where('identity_key', 'domaine or|golden chablis')->count())->toBe(1)
        ->and(SupplierParseProfile::whereNull('company_id')->count())->toBe(1);
});

it('fails clearly when no snapshot exists', function () {
    $this->artisan('wine:import-golden')->assertFailed();
});
