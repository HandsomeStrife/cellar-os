<?php

declare(strict_types=1);

use Domain\Catalogue\Enums\WineSubType;
use Domain\Catalogue\Enums\WineType;
use Domain\Import\Services\NormaliseService;
use Domain\Supplier\Actions\RecordUnmappedTypeLabelsAction;
use Domain\Supplier\Actions\SaveTypeMappingAction;
use Domain\Supplier\Models\Supplier;

it('files skin-contact wine as Orange without anyone teaching it', function () {
    $normalise = new NormaliseService;

    foreach (['Skin Contact', 'skin-contact', 'Skin Macerated', 'Amber', 'Ramato', 'Orange'] as $label) {
        expect($normalise->normaliseColour($label))->toBe(WineType::Orange);
    }
});

it('recognises the sparkling words a list actually prints', function () {
    $normalise = new NormaliseService;

    foreach (['Pet Nat', 'Pétillant', 'Frizzante', 'Champagne', 'Cava'] as $label) {
        expect($normalise->normaliseColour($label))->toBe(WineType::Sparkling);
    }
});

it('lets a supplier\'s own mapping override the shared vocabulary', function () {
    $normalise = (new NormaliseService)->withTypeMapping([
        // This supplier files their orange wines under a house word.
        'vin de voile' => ['type' => WineType::Orange->value],
    ]);

    expect($normalise->normaliseColour('Vin de Voile'))->toBe(WineType::Orange)
        // Case-insensitively, since a list is rarely consistent.
        ->and($normalise->normaliseColour('VIN DE VOILE'))->toBe(WineType::Orange);
});

it('applies a mapped sub-type on import when it belongs to the type', function () {
    $normalise = (new NormaliseService)->withTypeMapping([
        'bubbles (pink)' => ['type' => WineType::Sparkling->value, 'sub_type' => WineSubType::SparklingRose->value],
    ]);

    $product = $normalise->toProductData(
        ['Wine' => 'House Fizz', 'Type' => 'Bubbles (pink)'],
        ['wine_name' => 'Wine', 'colour' => 'Type'],
    );

    expect($product->colour)->toBe(WineType::Sparkling)
        ->and($product->sub_type)->toBe(WineSubType::SparklingRose);
});

it('ignores a mapped sub-type that belongs to a different type', function () {
    // A stale mapping must not file a red wine under a sparkling style.
    $normalise = (new NormaliseService)->withTypeMapping([
        'house red' => ['type' => WineType::Red->value, 'sub_type' => WineSubType::SparklingRose->value],
    ]);

    $product = $normalise->toProductData(
        ['Wine' => 'Vin de Table', 'Type' => 'House Red'],
        ['wine_name' => 'Wine', 'colour' => 'Type'],
    );

    expect($product->colour)->toBe(WineType::Red)
        ->and($product->sub_type)->toBeNull();
});

it('collects the type words it could not place', function () {
    $normalise = new NormaliseService;

    $normalise->normaliseColour('Vin de Voile');
    $normalise->normaliseColour('Red');
    $normalise->normaliseColour('Klarett');
    // Noise that isn't a type word at all is not offered for mapping.
    $normalise->normaliseColour('-');
    $normalise->normaliseColour('12');

    expect($normalise->unresolvedTypeLabels())->toEqualCanonicalizing(['Vin de Voile', 'Klarett']);
});

it('remembers a reviewer\'s mapping for the supplier\'s next list', function () {
    $supplier = Supplier::factory()->create();

    (new SaveTypeMappingAction)->execute($supplier->id, [
        'Vin de Voile' => ['type' => WineType::Orange->value, 'label' => 'Vin de Voile'],
    ]);

    $stored = $supplier->fresh()->type_mapping;

    expect($stored)->toHaveKey('vin de voile')
        ->and($stored['vin de voile']['type'])->toBe(WineType::Orange->value)
        ->and($stored['vin de voile']['label'])->toBe('Vin de Voile');
});

it('parks an unplaceable label as pending without overwriting a decision', function () {
    $supplier = Supplier::factory()->create();

    (new RecordUnmappedTypeLabelsAction)->execute($supplier->id, ['Vin de Voile', 'Klarett']);
    (new SaveTypeMappingAction)->execute($supplier->id, [
        'vin de voile' => ['type' => WineType::Orange->value],
    ]);

    // Re-analysing the document meets the same words again…
    (new RecordUnmappedTypeLabelsAction)->execute($supplier->id, ['Vin de Voile', 'Klarett']);

    $stored = $supplier->fresh()->type_mapping;

    // …and must not undo the human's decision.
    expect($stored['vin de voile']['type'])->toBe(WineType::Orange->value)
        ->and($stored['klarett']['type'])->toBeNull();
});

it('keeps a cleared mapping as pending rather than forgetting the word', function () {
    $supplier = Supplier::factory()->create();

    (new SaveTypeMappingAction)->execute($supplier->id, ['klarett' => ['type' => WineType::Rose->value]]);
    (new SaveTypeMappingAction)->execute($supplier->id, ['klarett' => ['type' => '']]);

    expect($supplier->fresh()->type_mapping)->toHaveKey('klarett')
        ->and($supplier->fresh()->type_mapping['klarett']['type'])->toBeNull();
});

it('does not carry one supplier\'s unplaceable words into the next', function () {
    // The weekly refresh reuses a single NormaliseService across every
    // supplier's document; the per-run accumulator must not leak between them.
    $service = new NormaliseService;

    $first = $service->withTypeMapping([]);
    $first->normaliseColour('Vin de Voile');
    expect($first->unresolvedTypeLabels())->toBe(['Vin de Voile']);

    $second = $first->withTypeMapping([]);
    $second->normaliseColour('Klarett');

    expect($second->unresolvedTypeLabels())->toBe(['Klarett']);
});
