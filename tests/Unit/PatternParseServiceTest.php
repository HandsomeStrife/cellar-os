<?php

declare(strict_types=1);

use Domain\Supplier\Services\DocumentTextExtractor;
use Domain\Supplier\Services\PatternParseService;

function cellRow(int $page, float $y, array $cells): array
{
    return [
        'page' => $page,
        'y' => $y,
        'cells' => array_map(fn (array $c) => ['text' => $c[1], 'x0' => (float) $c[0], 'x1' => (float) $c[0] + 10], $cells),
    ];
}

it('parses zoned rows with carry-down, sections, colour codes and format splitting', function () {
    // A Raeburn-style layout: left narrative (ignore), wine/style/vintage/format zones.
    $rules = [
        'zones' => [
            ['field' => 'ignore', 'x_min' => '0', 'x_max' => '250'],
            ['field' => 'wine_name', 'x_min' => '250', 'x_max' => '430'],
            ['field' => 'colour', 'x_min' => '430', 'x_max' => '460'],
            ['field' => 'vintage', 'x_min' => '460', 'x_max' => '485'],
            ['field' => 'format_ml', 'x_min' => '485', 'x_max' => '540'],
        ],
        'require' => ['wine_name', 'format_ml'],
        'carry' => ['region'],
        'section_regex' => '^(?<value>[A-Z\' ]+):$',
        'section_field' => 'region',
        'colour_map' => [['code' => 'w', 'colour' => 'White'], ['code' => 'r', 'colour' => 'Red']],
        'format_unit' => 'cl',
    ];

    $rows = [
        cellRow(107, 90, [[5, 'Organic certified, Suolo e Salute'], [272, 'ROERO ARNEIS:']]),
        cellRow(107, 100, [[5, 'narrative noise'], [272, 'Roero arneis'], [444, 'w'], [464, '2024'], [489, '75/6']]),
        cellRow(107, 110, [[272, 'Roero arneis'], [444, 'w'], [464, '2023'], [489, '75/6']]),
        cellRow(107, 120, [[272, 'BAROLO:']]),
        cellRow(107, 130, [[272, 'Barolo Brovia'], [444, 'r'], [464, '2021'], [489, '75/12']]),
        cellRow(107, 140, [[5, 'lone narrative line']]), // fully ignored -> not residue
    ];

    $result = (new PatternParseService)->parse($rows, $rules);

    expect($result['matched'])->toBe(3)
        ->and($result['residue'])->toBe(0);

    [$first, , $third] = $result['rows'];
    expect($first['wine_name'])->toBe('Roero arneis')
        ->and($first['colour'])->toBe('White')
        ->and($first['vintage'])->toBe('2024')
        ->and($first['format_ml'])->toBe('75cl')   // 75/6 split + cl unit
        ->and($first['case_size'])->toBe('6')
        ->and($first['region'])->toBe('ROERO ARNEIS') // section carried in
        ->and($first['_page'])->toBe('107');

    expect($third['wine_name'])->toBe('Barolo Brovia')
        ->and($third['colour'])->toBe('Red')
        ->and($third['region'])->toBe('BAROLO')
        ->and($third['case_size'])->toBe('12');
});

it('threads carry/section state across page batches (50-page boundary)', function () {
    $rules = [
        'zones' => [['field' => 'wine_name', 'x_min' => '0', 'x_max' => '300'], ['field' => 'unit_price', 'x_min' => '300', 'x_max' => '400'], ['field' => 'producer', 'x_min' => '400', 'x_max' => '500']],
        'require' => ['wine_name', 'unit_price'],
        'carry' => ['producer'],
        'section_regex' => '^(?<value>[A-Z ]+):$',
        'section_field' => 'region',
    ];
    $service = new PatternParseService;

    // Batch 1 (ends page 50): sets the section + producer context.
    $batch1 = $service->parse([
        cellRow(50, 10, [[10, 'BEAUJOLAIS:']]),
        cellRow(50, 20, [[10, 'Morgon VV'], [310, '12.00'], [410, 'Desvignes']]),
    ], $rules);

    // Batch 2 (starts page 51): rows rely on context from batch 1.
    $batch2 = $service->parse([
        cellRow(51, 10, [[10, 'Fleurie'], [310, '14.00']]),
    ], $rules, $batch1['state']);

    expect($batch2['rows'][0]['producer'])->toBe('Desvignes')   // carried across the boundary
        ->and($batch2['rows'][0]['region'])->toBe('BEAUJOLAIS'); // section survives too
});

it('lets regex captures override zone values and counts unmatched rows as residue', function () {
    $rules = [
        'zones' => [['field' => 'wine_name', 'x_min' => '0', 'x_max' => '400']],
        'row_regex' => '^(?<wine_name>.+?)\s+£(?<unit_price>[\d.]+)$',
        'require' => ['wine_name', 'unit_price'],
    ];

    $rows = [
        cellRow(1, 10, [[10, 'Chablis 2022'], [300, '£15.00']]),
        cellRow(1, 20, [[10, 'A heading without any price'], [200, 'still no price']]),
    ];

    $result = (new PatternParseService)->parse($rows, $rules);

    expect($result['matched'])->toBe(1)
        ->and($result['rows'][0]['wine_name'])->toBe('Chablis 2022')
        ->and($result['rows'][0]['unit_price'])->toBe('15.00')
        ->and($result['residue'])->toBe(1);
});

it('sanitises LLM-written rules (invalid regex, unknown fields, bad colours dropped)', function () {
    $clean = (new PatternParseService)->sanitise([
        'zones' => [
            ['field' => 'wine_name', 'x_min' => '10', 'x_max' => '99'],
            ['field' => 'hacker', 'x_min' => '0', 'x_max' => '1'],     // unknown field
            ['field' => 'colour', 'x_min' => 'NaN', 'x_max' => '50'],  // non-numeric
        ],
        'row_regex' => '(?<wine_name>[unclosed',                        // invalid PCRE
        'require' => ['wine_name', 'nonsense'],
        'carry' => ['producer'],
        'section_regex' => '^(?<value>[A-Z]+)$',
        'section_field' => 'region',
        'colour_map' => [['code' => 'r', 'colour' => 'Red'], ['code' => 'x', 'colour' => 'Purple']],
        'format_unit' => 'parsecs',
    ]);

    expect($clean['zones'])->toHaveCount(1)
        ->and($clean['row_regex'])->toBe('')
        ->and($clean['require'])->toBe(['wine_name'])
        ->and($clean['carry'])->toBe(['producer'])
        ->and($clean['section_regex'])->not->toBe('')
        ->and($clean['colour_map'])->toBe(['r' => 'Red'])
        ->and($clean['format_unit'])->toBe('');
});

it('parses bbox XML into y-clustered, x-sorted cell rows', function () {
    $xml = <<<'XML'
        <page width="595" height="842">
          <line xMin="124.10" yMin="338.77" xMax="176.97">
            <word xMin="124.10" yMin="338.77" xMax="141.47" yMax="346.37">GIANNI</word>
            <word xMin="143.02" yMin="338.77" xMax="176.97" yMax="346.37">MASCIARELLI,</word>
          </line>
          <line xMin="176.78" yMin="338.77" xMax="203.74">
            <word xMin="176.78" yMin="338.77" xMax="203.74" yMax="346.37">TREBBIANO</word>
          </line>
          <line xMin="455.00" yMin="339.10" xMax="480.00">
            <word xMin="455.00" yMin="339.10" xMax="480.00" yMax="346.37">&#163;12.40</word>
          </line>
          <line xMin="100.00" yMin="360.00" xMax="120.00">
            <word xMin="100.00" yMin="360.00" xMax="120.00" yMax="367.00">NEXT</word>
          </line>
        </page>
        XML;

    $rows = (new DocumentTextExtractor)->parseBboxXml($xml, 64);

    expect($rows)->toHaveCount(2);
    // Three cells cluster onto one visual row (yMin within tolerance), x-sorted.
    expect($rows[0]['page'])->toBe(64)
        ->and(array_column($rows[0]['cells'], 'text'))->toBe(['GIANNI MASCIARELLI,', 'TREBBIANO', '£12.40']);
    expect(array_column($rows[1]['cells'], 'text'))->toBe(['NEXT']);
});

it('applies multi-level section rules: static sets, named captures, clears and drop-cap folding', function () {
    // A Wright-style layout: type headers (letter-spaced drop caps), country
    // headers, and region+colour combo headers all shaping the same rows.
    $rules = [
        'zones' => [
            ['field' => 'wine_name', 'x_min' => '30', 'x_max' => '400'],
            ['field' => 'unit_price', 'x_min' => '500', 'x_max' => '555'],
            ['field' => 'format_ml', 'x_min' => '555', 'x_max' => '580'],
        ],
        'require' => ['wine_name', 'unit_price'],
        'sections' => [
            ['regex' => '^SPARKLING WINES?(?: \(continued\))?$', 'set' => ['colour' => 'Sparkling'], 'clears' => ['country', 'region']],
            ['regex' => '^CHAMPAGNES?(?: \(continued\))?$', 'set' => ['colour' => 'Sparkling', 'country' => 'France', 'region' => 'Champagne']],
            ['regex' => '^(?<region>[A-ZÉ ]+?) ?, ?(?<colour>RED|WHITE|ROSÉ)$'],
            ['regex' => '^(?<country>PORTUGAL|SPAIN|FRANCE)$'],
        ],
    ];

    $rows = [
        cellRow(5, 10, [[199, 'S PARKLING W INES (continued)']]),
        cellRow(5, 20, [[52, 'P ORTUGAL']]),
        cellRow(5, 30, [[52, 'Bairrada Sparkling, Luis Pato'], [543, '17.75']]),
        cellRow(5, 40, [[52, 'Some prose about the region that is not a wine.']]),
        cellRow(6, 10, [[254, 'C HAMPAGNE']]),
        cellRow(6, 20, [[37, 'Krug, Grande Cuvée, Reims'], [524, '140.00'], [558, '½']]),
        cellRow(7, 10, [[52, 'R IOJA , R ED']]),
        cellRow(7, 20, [[52, 'Rioja Reserva, La Rioja Alta'], [543, '25.00']]),
    ];

    $service = new PatternParseService;
    $result = $service->parse($rows, $rules);

    expect($result['matched'])->toBe(3);

    [$porto, $champagne, $rioja] = $result['rows'];
    expect($porto['colour'])->toBe('Sparkling')
        ->and($porto['country'])->toBe('Portugal')   // captured + title-cased
        ->and($porto)->not->toHaveKey('region');     // cleared by the type header

    expect($champagne['colour'])->toBe('Sparkling')
        ->and($champagne['country'])->toBe('France')
        ->and($champagne['region'])->toBe('Champagne')
        ->and($champagne['format_ml'])->toBe('½');   // half-bottle marker zone

    expect($rioja['region'])->toBe('Rioja')
        ->and($rioja['colour'])->toBe('Red')
        ->and($rioja['country'])->toBe('France');    // stale country: Champagne rule set it, Rioja rule did not clear
});

it('threads multi-section context across page batches via state', function () {
    $rules = [
        'zones' => [
            ['field' => 'wine_name', 'x_min' => '30', 'x_max' => '400'],
            ['field' => 'unit_price', 'x_min' => '500', 'x_max' => '580'],
        ],
        'require' => ['wine_name', 'unit_price'],
        'sections' => [['regex' => '^(?<colour>RED|WHITE) WINES$']],
    ];

    $service = new PatternParseService;
    $batchOne = $service->parse([cellRow(50, 10, [[52, 'RED WINES']])], $rules);
    $batchTwo = $service->parse([cellRow(51, 10, [[52, 'Barolo, Brovia'], [543, '30.00']])], $rules, $batchOne['state']);

    expect($batchTwo['rows'][0]['colour'])->toBe('Red');
});

it('discards rows in skip sections and outside the page window', function () {
    $rules = [
        'zones' => [
            ['field' => 'wine_name', 'x_min' => '30', 'x_max' => '400'],
            ['field' => 'unit_price', 'x_min' => '500', 'x_max' => '580'],
        ],
        'require' => ['wine_name', 'unit_price'],
        'pages' => ['min' => 3, 'max' => 91],
        'sections' => [
            ['regex' => '^RED WINES$', 'set' => ['colour' => 'Red']],
            ['regex' => '^(?:SPIRITS|SAKE)$', 'skip' => true, 'clears' => ['colour']],
        ],
    ];

    $rows = [
        cellRow(2, 10, [[52, 'Index Entry Wine'], [543, '6.00']]),      // before page window
        cellRow(5, 10, [[52, 'RED WINES']]),
        cellRow(5, 20, [[52, 'Barolo, Brovia'], [543, '30.00']]),
        cellRow(5, 30, [[52, 'SAKE']]),
        cellRow(5, 40, [[52, 'Junmai Ginjo'], [543, '25.00']]),          // in a skip section
        cellRow(6, 10, [[52, 'RED WINES']]),                             // resumes collection
        cellRow(6, 20, [[52, 'Chianti, Fontodi'], [543, '28.00']]),
        cellRow(93, 10, [[52, 'Whisky Thing'], [543, '40.00']]),         // after page window
    ];

    $service = new PatternParseService;
    $result = $service->parse($rows, $rules);

    expect(array_column($result['rows'], 'wine_name'))->toBe(['Barolo, Brovia', 'Chianti, Fontodi'])
        ->and($result['rows'][1]['colour'])->toBe('Red');

    // Skip state threads across batches.
    $batchOne = $service->parse([cellRow(50, 10, [[52, 'SPIRITS']])], $rules);
    $batchTwo = $service->parse([cellRow(51, 10, [[52, 'Gin Thing'], [543, '20.00']])], $rules, $batchOne['state']);
    expect($batchTwo['matched'])->toBe(0);
});
