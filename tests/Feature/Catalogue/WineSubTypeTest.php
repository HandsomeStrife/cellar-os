<?php

declare(strict_types=1);

use App\Livewire\Catalogue\Index;
use Domain\Catalogue\Enums\WineSubType;
use Domain\Catalogue\Enums\WineType;
use Domain\Catalogue\Models\Product;
use Domain\Catalogue\Repositories\ProductRepository;
use Domain\Catalogue\Support\WineTypeFromName;
use Domain\Import\Services\NormaliseService;
use Domain\Supplier\Actions\ConnectCompanyToSupplierAction;
use Domain\Supplier\Models\Supplier;
use Domain\User\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->supplier = Supplier::factory()->create(['name' => 'Sparkling Specialists']);
    (new ConnectCompanyToSupplierAction)->execute($this->user->company_id, $this->supplier->id);
});

it('files every sub-type under a parent type', function () {
    foreach (WineSubType::cases() as $subType) {
        expect($subType->parent())->toBeInstanceOf(WineType::class);
    }

    expect(WineSubType::forType(WineType::Sparkling))->toContain(WineSubType::SparklingRose)
        ->and(WineSubType::forType(WineType::Red))->toBe([]);
});

it('shortens a sub-type label against its parent', function () {
    expect(WineSubType::SparklingRose->getShortLabel())->toBe('Rosé')
        ->and(WineSubType::Port->getShortLabel())->toBe('Port');
});

it('infers a sparkling sub-type from the wine name', function () {
    expect(WineTypeFromName::inferSubType(WineType::Sparkling, 'Crémant de Loire Rosé Brut'))
        ->toBe(WineSubType::SparklingRose)
        // An unqualified sparkling wine is white — what almost all of them are.
        ->and(WineTypeFromName::inferSubType(WineType::Sparkling, 'Champagne Brut Réserve'))
        ->toBe(WineSubType::SparklingWhite)
        // "Blanc de Noirs" is still a white sparkling wine.
        ->and(WineTypeFromName::inferSubType(WineType::Sparkling, 'Champagne Blanc de Noirs'))
        ->toBe(WineSubType::SparklingWhite)
        ->and(WineTypeFromName::inferSubType(WineType::Sparkling, 'Bugey Cerdon Pétillant Naturel'))
        ->toBe(WineSubType::PetNat)
        // A stated colour outranks the method — a sparkling red pét-nat sits
        // with the sparkling reds on a list.
        ->and(WineTypeFromName::inferSubType(WineType::Sparkling, 'Pet Nat Red', 'Ancre Hill Estates'))
        ->toBe(WineSubType::SparklingRed)
        // Styles that are red without saying so…
        ->and(WineTypeFromName::inferSubType(WineType::Sparkling, 'Sparkling Shiraz, Black Queen, Barossa Valley'))
        ->toBe(WineSubType::SparklingRed)
        // …but a stated colour still wins over the style.
        ->and(WineTypeFromName::inferSubType(WineType::Sparkling, 'Lambrusco Bianco'))
        ->toBe(WineSubType::SparklingWhite);
});

it('infers a fortified sub-type from the style name', function () {
    expect(WineTypeFromName::inferSubType(WineType::Fortified, 'Taylor Fladgate Vintage Port'))
        ->toBe(WineSubType::Port)
        ->and(WineTypeFromName::inferSubType(WineType::Fortified, 'Tio Pepe Fino en Rama'))
        ->toBe(WineSubType::Sherry);
});

it('gives still wines no sub-type', function () {
    expect(WineTypeFromName::inferSubType(WineType::Red, 'Barolo Riserva'))->toBeNull()
        ->and(WineTypeFromName::inferSubType(WineType::Rose, 'Provence Rosé'))->toBeNull();
});

it('normalises a sub-type on import from either the type column or the name', function () {
    $normalise = new NormaliseService;

    $fromColumn = $normalise->toProductData(
        ['Wine' => 'Nyetimber Classic Cuvée', 'Type' => 'Sparkling Rosé'],
        ['wine_name' => 'Wine', 'colour' => 'Type'],
    );

    $fromName = $normalise->toProductData(
        ['Wine' => 'Crémant d\'Alsace Rosé', 'Type' => 'Sparkling'],
        ['wine_name' => 'Wine', 'colour' => 'Type'],
    );

    expect($fromColumn->colour)->toBe(WineType::Sparkling)
        ->and($fromColumn->sub_type)->toBe(WineSubType::SparklingRose)
        ->and($fromName->sub_type)->toBe(WineSubType::SparklingRose);
});

it('returns sub-typed wines when filtering by their parent type', function () {
    $rose = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'wine_name' => 'Crémant Rosé',
        'colour' => WineType::Sparkling,
        'sub_type' => WineSubType::SparklingRose,
    ]);
    $white = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'wine_name' => 'Champagne Brut',
        'colour' => WineType::Sparkling,
        'sub_type' => WineSubType::SparklingWhite,
    ]);
    Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'wine_name' => 'Barolo',
        'colour' => WineType::Red,
        'sub_type' => null,
    ]);

    $repository = new ProductRepository;

    // The parent type includes every sub-type…
    expect($repository->search(colour: WineType::Sparkling)->pluck('id')->all())
        ->toEqualCanonicalizing([$rose->id, $white->id])
        // …and the sub-type narrows within it.
        ->and($repository->search(colour: WineType::Sparkling, subType: WineSubType::SparklingRose)->pluck('id')->all())
        ->toBe([$rose->id]);
});

it('clears the sub-type filter when the type changes', function () {
    Livewire::test(Index::class)
        ->set('colour', WineType::Sparkling->value)
        ->set('sub_type', WineSubType::SparklingRose->value)
        ->set('colour', WineType::Red->value)
        ->assertSet('sub_type', '');
});
