<?php

declare(strict_types=1);

use Domain\Catalogue\Enums\WineType;
use Domain\Catalogue\Support\NonWineVocabulary;
use Domain\Catalogue\Support\WineTypeFromName;

it('infers colour deterministically from style words, appellations and grapes', function (string $name, ?string $producer, ?WineType $expected) {
    expect(WineTypeFromName::infer($name, $producer))->toBe($expected);
})->with([
    // Explicit style words win
    ['Vi Blanc, Xarel-lo/Macabeo (O)', 'Snou', WineType::White],
    ['Henri Gouges Nuits-St-Georges Blanc 1er Cru Perriere', null, WineType::White],
    ['Pleno Rosado', null, WineType::Rose],
    ['Bourgogne Passe-Tout-Grain Rose', 'Arnaud et Sophie', WineType::Rose],
    ['Sancerre Rouge, Domaine Dezat', null, WineType::Red],
    // Sparkling method words outrank colour words
    ['Blanc de Noirs, Fleury Père & Fils', 'Champagne Fleury', WineType::Sparkling],
    ["Combe Trousseau Pet'Nat", 'Stolpman Vineyards', WineType::Sparkling],
    ['Prosecco DOC Frizzante Corda', 'San Simone', WineType::Sparkling],
    ['Linea 27 Opere Lambrusco Amabile Emilia IGT', null, WineType::Sparkling],
    // Fortified styles
    ['Tanners Late Bottled Vintage Port 2019', 'Tanners', WineType::Fortified],
    ['Tanners Mariscal Manzanilla Sherry', 'Tanners', WineType::Fortified],
    ['Ratafia Vieillissement Exceptionnel Solera', 'Henri Giraud', WineType::Fortified],
    // Appellations / grapes one colour by definition
    ['Jean Collet & Fils Petit Chablis', 'Jean Collet', WineType::White],
    ['Domaine Arnaud et Sophie Meursault Grands Charrons', null, WineType::White],
    ['Domaine Arnaud et Sophie Gevrey-Chambertin En Champs', null, WineType::Red],
    ['Gunderloch Rothenberg Riesling Grosses Gewachs', null, WineType::White],
    ['Verdejo, Rueda', 'Valdeaces', WineType::White],
    ['Cerasuolo d\'Abruzzo "Le Vasche"', 'Caprera', WineType::Rose],
    // Honest nulls: nothing explicit, appellation ambiguous or unknown
    ['Clos de Tart Grand Cru', null, null],
    ['L\'Esthète (M), Sydonios, 2x460ml', 'Sydonios', null],
]);

it('marks spirits, sake, cider and water as non-wine but keeps fortified wines', function () {
    expect(NonWineVocabulary::matches('Grappa di Barbera Nibbio', 'Berta Distillerie'))->toBeTrue()
        ->and(NonWineVocabulary::matches('Hereford Finest Dry Gin', 'Tanners'))->toBeTrue()
        ->and(NonWineVocabulary::matches('Still Water, Velleminfroy', 'Eaux Minerales de Velleminfroy'))->toBeTrue()
        ->and(NonWineVocabulary::matches('Show Liqueur Muscat, South Eastern Australia', 'de Bortoli'))->toBeFalse()
        ->and(NonWineVocabulary::matches('Tanners Late Bottled Vintage Port 2019', 'Tanners'))->toBeFalse()
        ->and(NonWineVocabulary::matches('Vermouth Naturale Rosso', null))->toBeFalse();
});
