<?php

declare(strict_types=1);

use Domain\Admin\Models\Admin;
use Domain\Catalogue\Models\Product;
use Domain\Catalogue\Models\WineFact;
use Domain\Company\Models\Company;
use Domain\Supplier\Models\Supplier;
use Domain\Supplier\Models\SupplierParseProfile;
use Laravel\Sanctum\Sanctum;

function ingestAs(array $abilities = ['ingestion']): void
{
    Sanctum::actingAs(Admin::factory()->create(), $abilities);
}

it('rejects unauthenticated ingestion', function () {
    $this->postJson(route('api.ingest.suppliers'), ['rows' => [['name' => 'X']]])
        ->assertUnauthorized();
});

it('rejects tokens without the ingestion ability', function () {
    ingestAs(['something-else']);

    $this->postJson(route('api.ingest.suppliers'), ['rows' => [['name' => 'X']]])
        ->assertForbidden();
});

it('ingests suppliers, wines, facts and recipes end to end', function () {
    ingestAs();

    $this->postJson(route('api.ingest.suppliers'), ['rows' => [
        ['name' => 'Pushed Imports', 'country' => 'United Kingdom', 'onboarded_at' => '2026-06-01T00:00:00+00:00'],
    ]])->assertOk()->assertJson(['imported' => 1]);

    $this->postJson(route('api.ingest.wines'), ['rows' => [
        ['supplier' => 'Pushed Imports', 'wine_name' => 'Pushed Chablis', 'producer' => 'Domaine Push', 'grape' => ['Chardonnay'], 'colour' => 'White', 'vintage' => 2022, 'unit_price' => '18.00'],
        ['supplier' => 'Nobody Known', 'wine_name' => 'Orphan Wine'],
    ]])->assertOk()->assertJson(['imported' => 1, 'skipped' => 1]);

    $this->postJson(route('api.ingest.facts'), ['rows' => [
        ['identity_key' => 'domaine push|pushed chablis', 'wine_name' => 'Pushed Chablis', 'producer' => 'Domaine Push', 'region' => 'Burgundy', 'field_conflicts' => [], 'observations' => 2],
    ]])->assertOk()->assertJson(['imported' => 1]);

    $this->postJson(route('api.ingest.parse-profiles'), ['rows' => [
        ['supplier' => 'Pushed Imports', 'mode' => 'tabular', 'recipe' => ['mapping' => ['wine_name' => 'Wine']], 'confidence' => 0.9],
    ]])->assertOk()->assertJson(['imported' => 1]);

    $supplier = Supplier::firstWhere('name', 'Pushed Imports');
    expect($supplier->created_by_company_id)->toBeNull()
        ->and($supplier->onboarded_at)->not->toBeNull();
    expect(Product::where('wine_name', 'Pushed Chablis')->where('supplier_id', $supplier->id)->exists())->toBeTrue()
        ->and(Product::where('wine_name', 'Orphan Wine')->exists())->toBeFalse();
    expect(WineFact::firstWhere('identity_key', 'domaine push|pushed chablis')->region)->toBe('Burgundy')
        ->and(WineFact::firstWhere('identity_key', 'domaine push|pushed chablis')->observations)->toBe(2);
    expect(SupplierParseProfile::where('supplier_id', $supplier->id)->whereNull('company_id')->exists())->toBeTrue();

    $this->getJson(route('api.ingest.status'))
        ->assertOk()
        ->assertJsonStructure(['public_suppliers', 'products', 'wine_facts']);
});

it('cannot create or touch private suppliers through ingestion', function () {
    ingestAs();
    $company = Company::factory()->create();
    $private = Supplier::factory()->create(['name' => 'My Private', 'created_by_company_id' => $company->id, 'email' => 'keep@me.test']);

    // Same name pushed: creates/updates the PUBLIC record only.
    $this->postJson(route('api.ingest.suppliers'), ['rows' => [['name' => 'My Private', 'email' => 'new@public.test']]])
        ->assertOk();

    expect($private->fresh()->email)->toBe('keep@me.test')
        ->and(Supplier::where('name', 'My Private')->count())->toBe(2);
});

it('skips malformed rows instead of erroring (third-party feed resilience)', function () {
    ingestAs();
    Supplier::factory()->create(['name' => 'Resilient Imports']);

    $this->postJson(route('api.ingest.suppliers'), ['rows' => [
        ['name' => 'Resilient Imports', 'status' => 'NotAStatus', 'onboarded_at' => 'garbage-date'],
    ]])->assertOk();

    $this->postJson(route('api.ingest.wines'), ['rows' => [
        ['supplier' => 'Resilient Imports', 'wine_name' => 'Bad Colour Wine', 'colour' => 'Purple-ish'],
        ['supplier' => 'Resilient Imports', 'wine_name' => 'Good Wine', 'colour' => 'Red'],
    ]])->assertOk()->assertJson(['imported' => 1, 'skipped' => 1]);

    $this->postJson(route('api.ingest.facts'), ['rows' => [
        ['identity_key' => 'x|bad colour', 'wine_name' => 'Bad Colour', 'colour' => 'Purple-ish'],
    ]])->assertOk()->assertJson(['imported' => 1]); // colour dropped, row kept

    expect(Product::where('wine_name', 'Good Wine')->exists())->toBeTrue()
        ->and(Product::where('wine_name', 'Bad Colour Wine')->exists())->toBeFalse()
        ->and(WineFact::firstWhere('identity_key', 'x|bad colour')->colour)->toBeNull();
});

it('enforces the batch cap', function () {
    ingestAs();

    $this->postJson(route('api.ingest.suppliers'), ['rows' => array_fill(0, 501, ['name' => 'X'])])
        ->assertUnprocessable();
});
