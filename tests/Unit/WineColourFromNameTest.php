<?php

declare(strict_types=1);

use Domain\Catalogue\Enums\WineColour;
use Domain\Catalogue\Support\NonWineVocabulary;
use Domain\Catalogue\Support\WineColourFromName;

it('infers colour deterministically from style words, appellations and grapes', function (string $name, ?string $producer, ?WineColour $expected) {
    expect(WineColourFromName::infer($name, $producer))->toBe($expected);
})->with([
    // Explicit style words win
    ['Vi Blanc, Xarel-lo/Macabeo (O)', 'Snou', WineColour::White],
    ['Henri Gouges Nuits-St-Georges Blanc 1er Cru Perriere', null, WineColour::White],
    ['Pleno Rosado', null, WineColour::Rose],
    ['Bourgogne Passe-Tout-Grain Rose', 'Arnaud et Sophie', WineColour::Rose],
    ['Sancerre Rouge, Domaine Dezat', null, WineColour::Red],
    // Sparkling method words outrank colour words
    ['Blanc de Noirs, Fleury Père & Fils', 'Champagne Fleury', WineColour::Sparkling],
    ["Combe Trousseau Pet'Nat", 'Stolpman Vineyards', WineColour::Sparkling],
    ['Prosecco DOC Frizzante Corda', 'San Simone', WineColour::Sparkling],
    ['Linea 27 Opere Lambrusco Amabile Emilia IGT', null, WineColour::Sparkling],
    // Fortified styles
    ['Tanners Late Bottled Vintage Port 2019', 'Tanners', WineColour::Fortified],
    ['Tanners Mariscal Manzanilla Sherry', 'Tanners', WineColour::Fortified],
    ['Ratafia Vieillissement Exceptionnel Solera', 'Henri Giraud', WineColour::Fortified],
    // Appellations / grapes one colour by definition
    ['Jean Collet & Fils Petit Chablis', 'Jean Collet', WineColour::White],
    ['Domaine Arnaud et Sophie Meursault Grands Charrons', null, WineColour::White],
    ['Domaine Arnaud et Sophie Gevrey-Chambertin En Champs', null, WineColour::Red],
    ['Gunderloch Rothenberg Riesling Grosses Gewachs', null, WineColour::White],
    ['Verdejo, Rueda', 'Valdeaces', WineColour::White],
    ['Cerasuolo d\'Abruzzo "Le Vasche"', 'Caprera', WineColour::Rose],
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
