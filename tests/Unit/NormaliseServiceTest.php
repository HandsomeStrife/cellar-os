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

it('returns null for a row without a wine name', function () {
    expect($this->svc->toProductData(['Name' => ''], ['wine_name' => 'Name']))->toBeNull();
});
