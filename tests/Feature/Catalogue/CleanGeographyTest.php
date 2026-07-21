<?php

declare(strict_types=1);

use Domain\Catalogue\Actions\CleanCatalogueGeographyAction;
use Domain\Catalogue\Models\Product;
use Domain\Supplier\Models\Supplier;

beforeEach(function () {
    $this->supplier = Supplier::factory()->create();
});

function makeProduct(array $attrs): Product
{
    return Product::factory()->create(array_merge([
        'supplier_id' => test()->supplier->id,
        'country' => null, 'region' => null, 'sub_region' => null,
    ], $attrs));
}

it('promotes the real region out of sub_region when region holds the country', function () {
    $p = makeProduct(['country' => 'Italy', 'region' => 'Italy', 'sub_region' => 'Toscana']);

    (new CleanCatalogueGeographyAction)->execute();

    $fresh = $p->fresh();
    expect($fresh->country)->toBe('Italy')
        ->and($fresh->region)->toBe('Toscana')
        ->and($fresh->sub_region)->toBeNull();
});

it('clears the region when it merely duplicates the country and there is no finer region', function () {
    $p = makeProduct(['country' => 'Germany', 'region' => 'Germany', 'sub_region' => null]);

    (new CleanCatalogueGeographyAction)->execute();

    $fresh = $p->fresh();
    expect($fresh->country)->toBe('Germany')
        ->and($fresh->region)->toBeNull();
});

it('fills an empty country from a country-in-region value', function () {
    $p = makeProduct(['country' => null, 'region' => 'Romania']);

    (new CleanCatalogueGeographyAction)->execute();

    $fresh = $p->fresh();
    expect($fresh->country)->toBe('Romania')
        ->and($fresh->region)->toBeNull();
});

it('leaves genuine sub-national regions that share no country name alone', function () {
    $sa = makeProduct(['country' => 'Australia', 'region' => 'South Australia']);
    $burgundy = makeProduct(['country' => 'France', 'region' => 'Burgundy']);

    (new CleanCatalogueGeographyAction)->execute();

    expect($sa->fresh()->region)->toBe('South Australia')
        ->and($burgundy->fresh()->region)->toBe('Burgundy');
});

it('recovers a junk country string without discarding the wine', function () {
    $nfd = makeProduct(['country' => 'Italy - NFD', 'region' => 'Toscana', 'unit_price' => '32.15']);
    $sherry = makeProduct(['country' => 'Spain                    SHERRY - CLASSIFIED LIST£8.35', 'region' => 'Andalucia']);

    (new CleanCatalogueGeographyAction)->execute();

    expect($nfd->fresh()->country)->toBe('Italy')
        ->and($nfd->fresh()->archived_at)->toBeNull()   // kept — it's a real wine
        ->and($sherry->fresh()->country)->toBe('Spain');
});

it('archives a non-wine section header row', function () {
    $keg = makeProduct(['wine_name' => 'KEG/KEYKEGS', 'country' => 'CLASSIFIED']);

    (new CleanCatalogueGeographyAction)->execute();

    expect($keg->fresh()->archived_at)->not->toBeNull();
});

it('canonicalises duplicate country spellings', function () {
    $a = makeProduct(['country' => 'United States', 'region' => 'Napa Valley']);
    $b = makeProduct(['country' => 'USA', 'region' => 'Sonoma']);

    (new CleanCatalogueGeographyAction)->execute();

    expect($a->fresh()->country)->toBe('USA')
        ->and($b->fresh()->country)->toBe('USA');
});

it('moves a macro-region parked in the country column into region', function () {
    $p = makeProduct(['country' => 'South-West France', 'region' => null]);

    (new CleanCatalogueGeographyAction)->execute();

    $fresh = $p->fresh();
    expect($fresh->country)->toBe('France')
        ->and($fresh->region)->toBe('South-West France');
});

it('is idempotent', function () {
    makeProduct(['country' => 'Italy', 'region' => 'Italy', 'sub_region' => 'Toscana']);
    makeProduct(['country' => 'United States', 'region' => 'Napa Valley']);

    (new CleanCatalogueGeographyAction)->execute();
    $second = (new CleanCatalogueGeographyAction)->execute();

    expect($second)->toBe([
        'archived' => 0, 'region_demoted' => 0, 'region_cleared' => 0,
        'country_filled' => 0, 'region_recovered' => 0, 'country_canonicalised' => 0,
        'sub_region_dedup' => 0,
    ]);
});

it('clears a sub_region that merely duplicates the region', function () {
    // Mirrors the prod artifact: golden overwrote region but could not blank
    // the now-redundant sub_region carrying the same value.
    $p = makeProduct(['country' => 'Italy', 'region' => 'Toscana', 'sub_region' => 'Toscana']);

    $stats = (new CleanCatalogueGeographyAction)->execute();

    expect($stats['sub_region_dedup'])->toBe(1)
        ->and($p->fresh()->region)->toBe('Toscana')
        ->and($p->fresh()->sub_region)->toBeNull();
});

it('reports counts without writing when apply is false', function () {
    $p = makeProduct(['country' => 'Italy', 'region' => 'Italy', 'sub_region' => 'Toscana']);

    $stats = (new CleanCatalogueGeographyAction)->execute(apply: false);

    expect($stats['region_demoted'])->toBe(1)
        ->and($p->fresh()->region)->toBe('Italy'); // unchanged
});
