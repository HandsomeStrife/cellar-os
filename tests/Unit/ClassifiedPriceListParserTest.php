<?php

declare(strict_types=1);

use Domain\Supplier\Services\ClassifiedPriceListParser;

beforeEach(function () {
    $this->parser = new ClassifiedPriceListParser;
});

/**
 * A realistic slice of a "CLASSIFIED PRICE CHECK" index: a producer-grid line
 * first (must be ignored), then the heading, sections, two-line records, a
 * non-alcoholic row, and a format-bucket (no colour) record.
 */
function classifiedFixture(): string
{
    return implode("\n", [
        // --- producer-grid noise that precedes the index (must be skipped) ---
        '      UNITED KINGDOM - WALES         2023  CHARDONNAY',
        '                                             ANCRE HILL      White      £20.10   75.00cl',
        '',
        '                                              CLASSIFIED PRICE CHECK',
        '                                                     *Bold denotes new listing',
        '                                     WHITE WINES',
        '      2024    Le Lesc, Côtes de Gascogne IGP - South-West France                 WHITE WINES - CLASSIFIED',
        '                                                                                 £7.05 LIST    75.00cl    11.00%',
        '      2023    Chardonnay, Ancre Hill - Monmouthshire, Wales                      WHITE WINES - CLASSIFIED',
        '                                                                                 £20.10LIST    75.00cl    9.50%',
        '      NV      Substance/s, Myrko Tépus (Green Tea) (Non Alcoholic) Fermented     WHITE WINES - CLASSIFIED',
        '                                                                                 £11.95    75.00cl    0.00%',
        '                                     RED WINES',
        '      2020    Pinot Noir, Ancre Hill - Monmouthshire, Wales                      RED WINES - CLASSIFIED',
        '                                                                                 £20.20LIST    75.00cl    11.00%',
        '                                     SPARKLING WINES',
        '      NV      Pet Nat Red, Ancre Hill Estates - Monmouthshire, Wales             SPARKLING WINES - CLASSIFIED',
        '                                                                                 £16.50    LIST',
        '                                     ROSÉ WINES',
        '      2023    Bandol Rosé, Château de Pibarnon - Provence, France                ROSÉ WINES - CLASSIFIED',
        '                                                                                 £30.05 LIST',
        '                                     MAGNUMS',
        '      2021    Some Big Bottle, A Producer - Rhone, France                        MAGNUMS - CLASSIFIED',
        '                                                                                 £48.00LIST    150.00cl    13.00%',
        // A sizeless magnum of the SAME wine as a 750ml bottle above — must NOT
        // collapse onto it (that was the price-overwrite bug).
        '      2023    Chardonnay, Ancre Hill - Monmouthshire, Wales                      MAGNUMS - CLASSIFIED',
        '                                                                                 £40.20',
        '                                     HALF BOTTLES',
        '      2022    Little Red, Some Grower - Loire, France                            HALF BOTTLES - CLASSIFIED LIST',
        '                                                                                 £6.50',
        '                                     BAG-IN-BOX',
        '      2024    Le Lesc Blanc, Côtes de Gascogne - South-West France - 10 Litre BIB    BAG-IN-BOX - CLASSIFIED',
        '                                                                                 £79.35',
    ]);
}

it('detects a classified price-check document', function () {
    expect($this->parser->looksClassified(classifiedFixture()))->toBeTrue()
        ->and($this->parser->looksClassified("just some wine list\n2020 Something £10.00"))->toBeFalse();
});

it('detects classification by a run of per-row tags without the heading', function () {
    $rows = str_repeat("2020 Wine X, Producer - Loire, France   RED WINES - CLASSIFIED\n", 25);
    expect($this->parser->looksClassified($rows))->toBeTrue();
});

it('parses the index and ignores the producer-grid lines before it', function () {
    $rows = $this->parser->parseIndex(classifiedFixture());

    // whites: Le Lesc, Chardonnay (Substance/s dropped as non-alcoholic);
    // red: Pinot Noir; sparkling: Pet Nat Red; rosé: Bandol Rosé;
    // magnums: Some Big Bottle + Chardonnay magnum; half: Little Red; BIB: Le Lesc Blanc = 9.
    expect($rows)->toHaveCount(9);

    $names = array_map(fn ($r) => $r['fields']['wine_name'], $rows);
    expect($names)->toContain('Le Lesc', 'Chardonnay', 'Pinot Noir', 'Pet Nat Red', 'Bandol Rosé')
        ->not->toContain('Substance/s'); // non-alcoholic excluded
    // The grid Chardonnay line before the heading must not double-count, but the
    // index's own bottle + magnum Chardonnay are two distinct rows.
    expect(array_filter($names, fn ($n) => $n === 'Chardonnay'))->toHaveCount(2);
});

it('derives colour from the style tag and reads exact prices', function () {
    $rows = collect($this->parser->parseIndex(classifiedFixture()));
    $find = fn (string $name, ?string $price = null) => $rows->first(
        fn ($r) => $r['fields']['wine_name'] === $name && ($price === null || ($r['fields']['unit_price'] ?? null) === $price)
    )['fields'];

    expect($find('Chardonnay', '20.10'))
        ->colour->toBe('White')
        ->unit_price->toBe('20.10')
        ->producer->toBe('Ancre Hill')
        ->region->toBe('Monmouthshire')
        ->country->toBe('Wales')
        ->vintage->toBe('2023');

    expect($find('Pinot Noir')['colour'])->toBe('Red');
    expect($find('Pet Nat Red')['colour'])->toBe('Sparkling');
    // Format buckets carry no colour — left for backfill.
    expect($find('Some Big Bottle'))->not->toHaveKey('colour');
});

it('sizes format-section rows so they do not collide with the 750ml bottle', function () {
    $byName = collect($this->parser->parseIndex(classifiedFixture()))
        ->groupBy(fn ($r) => $r['fields']['wine_name']);

    // The sizeless magnum keeps the magnum price AND a magnum size (150cl →
    // 1500ml via NormaliseService) so it never overwrites the £20.10 bottle.
    $chards = $byName['Chardonnay'];
    expect($chards)->toHaveCount(2);
    $bottle = $chards->firstWhere(fn ($r) => ($r['fields']['unit_price'] ?? null) === '20.10');
    $magnum = $chards->firstWhere(fn ($r) => ($r['fields']['unit_price'] ?? null) === '40.20');
    expect($bottle)->not->toBeNull()
        ->and($magnum['fields']['format_ml'])->toBe('150cl');

    // Sizeless half-bottle → 37.5cl.
    expect($byName['Little Red'][0]['fields']['format_ml'])->toBe('37.5cl');

    // Rosé section → Rosé colour.
    expect($byName['Bandol Rosé'][0]['fields']['colour'])->toBe('Rosé');
});

it('reads a bag-in-box size from the name and keeps it out of the country', function () {
    $row = collect($this->parser->parseIndex(classifiedFixture()))
        ->firstWhere(fn ($r) => $r['fields']['wine_name'] === 'Le Lesc Blanc');

    expect($row['fields']['format_ml'])->toBe('1000cl') // 10 Litre
        ->and($row['fields']['country'])->toBe('South-West France')
        ->and($row['fields']['country'])->not->toContain('BIB');
});

it('parses a price line that has no size/abv (sparkling shape)', function () {
    $rows = collect($this->parser->parseIndex(classifiedFixture()))
        ->keyBy(fn ($r) => $r['fields']['wine_name']);

    expect($rows['Pet Nat Red']['fields']['unit_price'])->toBe('16.50');
});

it('splits the packed descriptor into columns', function () {
    expect($this->parser->splitDescriptor('Chardonnay, Ancre Hill - Monmouthshire, Wales'))
        ->toBe(['Chardonnay', 'Ancre Hill', 'Monmouthshire', 'Wales']);

    // No region — just a country after the dash.
    expect($this->parser->splitDescriptor('Elevate White, Mersel Wine - Lebanon'))
        ->toBe(['Elevate White', 'Mersel Wine', null, 'Lebanon']);

    // No producer — dash straight to region.
    expect($this->parser->splitDescriptor('Le Lesc - South-West France'))
        ->toBe(['Le Lesc', null, null, 'South-West France']);

    // Bare name.
    expect($this->parser->splitDescriptor('Mystery Wine'))
        ->toBe(['Mystery Wine', null, null, null]);
});

it('extracts grape from OCR grid lines and ignores noise', function () {
    $ocr = [72 => implode("\n", [
        '2021 ~~ ZOLD ~ Sylvaner                                    Orange      £21.50   75.00cl   11.00%',
        '2024    SAINT-CIRICE ROUGE ~ Syrah, Grenache, Merlot       Red         £9.10    75.00cl   13.50%',
        '2023    CHARDONNAY                                         White       £20.10   75.00cl   9.50%',  // no grape
        'ANCRE HILL ESTATES, RICHARD & JOY MORRIS, Monmouthshire',                                          // header, no price
    ])];

    $records = $this->parser->extractGridGrapes($ocr);

    expect($records)->toHaveCount(2);
    $byName = collect($records)->keyBy('name');
    expect($byName)->toHaveKey('ZOLD');
    expect($byName['ZOLD']['grape'])->toBe('Sylvaner');
    expect($byName['ZOLD']['price'])->toBe(21.50);
    expect($byName['SAINT-CIRICE ROUGE']['grape'])->toBe('Syrah, Grenache, Merlot');
});

it('merges grape onto matching backbone wines by vintage+price+name', function () {
    $backbone = [
        ['fields' => ['wine_name' => 'Zold', 'vintage' => '2021', 'unit_price' => '21.50'], 'page' => 100],
        ['fields' => ['wine_name' => 'Chardonnay', 'vintage' => '2023', 'unit_price' => '20.10'], 'page' => 100],
    ];
    $grid = [
        ['vintage' => '2021', 'price' => 21.50, 'name' => 'ZOLD', 'grape' => 'Sylvaner', 'producer' => null],
    ];

    $result = $this->parser->mergeGrapes($backbone, $grid);

    expect($result['enriched'])->toBe(1);
    expect($result['rows'][0]['fields']['grape'])->toBe('Sylvaner');
    // No matching grid record → left untouched.
    expect($result['rows'][1]['fields'])->not->toHaveKey('grape');
});

it('does not bleed grape across a price mismatch or overwrite an existing grape', function () {
    $backbone = [
        // Same vintage+name token but a different price → must NOT match.
        ['fields' => ['wine_name' => 'Zold', 'vintage' => '2021', 'unit_price' => '99.00'], 'page' => 1],
        // Already has a grape → must be preserved.
        ['fields' => ['wine_name' => 'Zold', 'vintage' => '2021', 'unit_price' => '21.50', 'grape' => 'Existing'], 'page' => 1],
    ];
    $grid = [
        ['vintage' => '2021', 'price' => 21.50, 'name' => 'ZOLD', 'grape' => 'Sylvaner', 'producer' => null],
    ];

    $result = $this->parser->mergeGrapes($backbone, $grid);

    expect($result['enriched'])->toBe(0);
    expect($result['rows'][0]['fields'])->not->toHaveKey('grape');
    expect($result['rows'][1]['fields']['grape'])->toBe('Existing');
});
