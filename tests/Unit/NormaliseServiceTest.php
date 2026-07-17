<?php

declare(strict_types=1);

use Domain\Catalogue\Enums\WineColour;
use Domain\Import\Services\NormaliseService;

beforeEach(function () {
    $this->svc = new NormaliseService;
});

it('maps colours across languages and styles', function () {
    expect($this->svc->normaliseColour('Rouge'))->toBe(WineColour::Red)
        ->and($this->svc->normaliseColour('Bianco'))->toBe(WineColour::White)
        ->and($this->svc->normaliseColour('Champagne'))->toBe(WineColour::Sparkling)
        ->and($this->svc->normaliseColour('Tawny Port'))->toBe(WineColour::Fortified)
        ->and($this->svc->normaliseColour('Rosado'))->toBe(WineColour::Rose)
        ->and($this->svc->normaliseColour('Orange'))->toBe(WineColour::Orange)
        ->and($this->svc->normaliseColour(''))->toBeNull()
        ->and($this->svc->normaliseColour('mystery'))->toBeNull();
});

it('splits grapes on common separators', function () {
    expect($this->svc->parseGrapes('Cabernet Sauvignon, Merlot / Cabernet Franc'))
        ->toBe(['Cabernet Sauvignon', 'Merlot', 'Cabernet Franc'])
        ->and($this->svc->parseGrapes('Syrah and Grenache'))->toBe(['Syrah', 'Grenache'])
        ->and($this->svc->parseGrapes(''))->toBeNull();
});

it('parses prices with currency symbols and separators', function () {
    expect($this->svc->parsePrice('£1,234.50'))->toBe(1234.50)
        ->and($this->svc->parsePrice('25,00'))->toBe(25.0)
        ->and($this->svc->parsePrice('€ 18.00'))->toBe(18.0)
        ->and($this->svc->parsePrice('1,200'))->toBe(1200.0)       // comma thousands, no decimals
        ->and($this->svc->parsePrice('1,234,567'))->toBe(1234567.0) // multiple thousands separators
        ->and($this->svc->parsePrice(''))->toBeNull()
        ->and($this->svc->parsePrice('POA'))->toBeNull();
});

it('parses vintages and handles NV', function () {
    expect($this->svc->parseVintage('2019'))->toBe(2019)
        ->and($this->svc->parseVintage('Vintage 2017'))->toBe(2017)
        ->and($this->svc->parseVintage('NV'))->toBeNull()
        ->and($this->svc->parseVintage(''))->toBeNull();
});

it('parses bottle formats', function () {
    expect($this->svc->parseFormatMl('Magnum'))->toBe(1500)
        ->and($this->svc->parseFormatMl('750ml'))->toBe(750)
        ->and($this->svc->parseFormatMl('75cl'))->toBe(750)
        ->and($this->svc->parseFormatMl('1.5L'))->toBe(1500)
        ->and($this->svc->parseFormatMl('Half bottle'))->toBe(375)
        ->and($this->svc->parseFormatMl('½'))->toBe(375)
        ->and($this->svc->parseFormatMl(''))->toBe(750);
});

it('builds normalised ProductData from a mapped row', function () {
    $row = ['Name' => 'Test Red', 'Colour' => 'Red', 'Year' => '2019', 'Price' => '£30.00', 'Format' => '750ml'];
    $mapping = ['wine_name' => 'Name', 'colour' => 'Colour', 'vintage' => 'Year', 'unit_price' => 'Price', 'format_ml' => 'Format'];

    $product = $this->svc->toProductData($row, $mapping, supplierId: 5, rawUploadId: 9);

    expect($product)->not->toBeNull()
        ->and($product->wine_name)->toBe('Test Red')
        ->and($product->colour)->toBe(WineColour::Red)
        ->and($product->vintage)->toBe(2019)
        ->and($product->unit_price)->toBe('30.00')
        ->and($product->price_per_litre)->toBe('40.00')
        ->and($product->supplier_id)->toBe(5)
        ->and($product->raw_upload_id)->toBe(9);
});

it('parses a combined "NxSIZE" pack cell into case size and bottle size', function () {
    // The Flint Wines defect: a "Case Size" cell of "12x75cl" was digit-stripped
    // to case_size 1275. It must read as a case of 12 × 750ml.
    $build = fn (string $caseCell) => $this->svc->toProductData(
        ['Name' => 'Pack Wine', 'Pack' => $caseCell, 'Price' => '24.00'],
        ['wine_name' => 'Name', 'case_size' => 'Pack', 'unit_price' => 'Price'],
    );

    expect($build('12x75cl')->case_size)->toBe(12)
        ->and($build('12x75cl')->format_ml)->toBe(750)
        ->and($build('6x75cl')->case_size)->toBe(6)
        ->and($build('3x150cl')->case_size)->toBe(3)        // 3 magnums
        ->and($build('3x150cl')->format_ml)->toBe(1500)
        ->and($build('12x37.5cl')->case_size)->toBe(12)     // 12 half bottles
        ->and($build('12x37.5cl')->format_ml)->toBe(375)
        ->and($build('6x1.5L')->format_ml)->toBe(1500)
        ->and($build('6')->case_size)->toBe(6)              // a plain numeric case size still works
        ->and($build('6')->format_ml)->toBe(750);
});

it('lets an explicit bottle-size column override the pack-derived size', function () {
    $product = $this->svc->toProductData(
        ['Name' => 'X', 'Pack' => '6x75cl', 'Btl' => 'Magnum', 'Price' => '10'],
        ['wine_name' => 'Name', 'case_size' => 'Pack', 'format_ml' => 'Btl', 'unit_price' => 'Price'],
    );

    expect($product->case_size)->toBe(6)
        ->and($product->format_ml)->toBe(1500);
});

it('derives case pricing when a per-case price column accompanies a pack cell', function () {
    // Flint's current file: "Case Size" 12x75cl, per-bottle £24, per-case £288.
    $product = $this->svc->toProductData(
        ['Name' => 'A Lisa Malbec', 'Pack' => '12x75cl', 'Btl' => '24', 'Case' => '288'],
        ['wine_name' => 'Name', 'case_size' => 'Pack', 'unit_price' => 'Btl', 'pack_price' => 'Case'],
    );

    expect($product->case_size)->toBe(12)
        ->and($product->sold_by->value)->toBe('case')
        ->and($product->unit_price)->toBe('24.00')
        ->and($product->pack_price)->toBe('288.00');
});

it('returns null for a row without a wine name', function () {
    expect($this->svc->toProductData(['Name' => ''], ['wine_name' => 'Name']))->toBeNull();
});

it('standardises grape and region aliases', function () {
    expect($this->svc->parseGrapes('Shiraz, Cab Sauv'))->toBe(['Syrah', 'Cabernet Sauvignon'])
        ->and($this->svc->standardiseRegion('burgundy'))->toBe('Bourgogne')
        ->and($this->svc->standardiseRegion('Napa Valley'))->toBe('Napa Valley');
});

it('geocodes from region then country, deterministically', function () {
    $byRegion = $this->svc->geocode('Bourgogne', 'France', 'Some Wine');
    expect($byRegion)->toHaveKeys(['lat', 'lng'])
        ->and((float) $byRegion['lat'])->toBeGreaterThan(46.0)->toBeLessThan(48.0);

    // Same seed → same coords (deterministic).
    expect($this->svc->geocode('Bourgogne', 'France', 'Some Wine'))->toBe($byRegion);

    // Falls back to country when region unknown.
    $byCountry = $this->svc->geocode('Nowhere', 'Italy', 'X');
    expect((float) $byCountry['lat'])->toBeGreaterThan(40.0)->toBeLessThan(43.0);

    // Unknown region + country → no coords.
    expect($this->svc->geocode('Nowhere', 'Atlantis', 'X'))->toBe([]);
});

it('attaches coordinates to an imported product', function () {
    $product = $this->svc->toProductData(
        ['Name' => 'Test', 'Country' => 'France', 'Region' => 'Bordeaux'],
        ['wine_name' => 'Name', 'country' => 'Country', 'region' => 'Region'],
    );

    expect($product->latitude)->not->toBeNull()
        ->and($product->longitude)->not->toBeNull();
});
