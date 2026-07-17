<?php

declare(strict_types=1);

namespace Domain\Catalogue\Support;

use Domain\Catalogue\Enums\WineColour;

/**
 * Deterministic colour inference from a wine's NAME — the same class of
 * knowledge as the region→country map: explicit style words first ("Blanc",
 * "Rosado", "Port"), then appellations and grape varieties that are one
 * colour by definition (Chablis is white; Gevrey-Chambertin is red; Verdejo
 * is a white grape). Curated and word-boundary matched — anything ambiguous
 * (Sancerre-style both-colour appellations are ordered after style words,
 * genuinely mixed ones are omitted) returns null rather than guessing.
 * No statistics, no LLM: every mapping is checkable against the appellation
 * rules or ampelography.
 *
 * Used by the catalogue backfill for wines whose supplier list carries no
 * colour column or section (fill-only — a supplier-given colour always wins).
 */
class WineColourFromName
{
    /** Style/method words meaning sparkling, regardless of what else the name says. */
    private const SPARKLING = [
        'champagne', 'crémant', 'cremant', 'prosecco', 'franciacorta', 'spumante',
        'frizzante', 'lambrusco', 'blanquette', 'sekt', 'espumante', 'espumoso',
        'pét-nat', 'pet-nat', "pet'nat", 'pétnat', 'petnat', 'pétillant', 'petillant',
        'metodo classico', 'méthode traditionnelle', 'mousseux',
    ];

    /** Fortified-wine styles. */
    private const FORTIFIED = [
        'port', 'sherry', 'manzanilla', 'amontillado', 'oloroso', 'palo cortado',
        'madeira', 'marsala', 'ratafia', 'vermouth', 'vin doux naturel',
        'liqueur muscat', 'muscat liqueur', 'vin de liqueur',
    ];

    /** Sweet-wine styles and appellations. */
    private const DESSERT = [
        'sauternes', 'barsac', 'monbazillac', 'eiswein', 'ice wine', 'icewine',
        'beerenauslese', 'trockenbeerenauslese', 'vin santo', 'moelleux',
        'vendange tardive', 'vendanges tardives', 'late harvest', 'passito',
        'black muscat', 'orange muscat',
    ];

    private const ROSE_WORDS = ['rosé', 'rose', 'rosado', 'rosato', 'cerasuolo'];

    private const WHITE_WORDS = ['blanc', 'bianco', 'blanco', 'branco', 'weiss', 'weisser', 'weisswein', 'white'];

    private const RED_WORDS = ['rouge', 'rosso', 'tinto', 'rotwein', 'red'];

    /** Appellations and grapes that are white by definition. */
    private const WHITE_BY_DEFINITION = [
        // Burgundy & nearby
        'chablis', 'saint-bris', 'st-bris', 'meursault', 'puligny-montrachet',
        'chassagne-montrachet', 'montrachet', 'aligoté', 'aligote',
        'pouilly-fuissé', 'pouilly-fuisse', 'pouilly-fumé', 'pouilly-fume',
        'saint-véran', 'st-véran', 'saint-veran', 'st-veran',
        'corton-charlemagne', 'corton charlemagne',
        'mâcon-villages', 'macon-villages', 'viré-clessé', 'vire-clesse',
        'vin jaune', 'château-chalon', 'chateau-chalon',
        'muscadet', 'sancerre', 'vouvray', 'savennières', 'savennieres', 'condrieu', 'picpoul',
        // White grapes
        'riesling', 'chardonnay', 'sauvignon blanc', 'chenin', 'viognier',
        'gewurztraminer', 'gewürztraminer', 'grüner veltliner', 'gruner veltliner',
        'verdejo', 'albariño', 'albarino', 'godello', 'garganega', 'verdicchio',
        'vermentino', 'assyrtiko', 'furmint', 'silvaner', 'pinot gris', 'pinot grigio',
        'txakoli', 'txakolina', 'hondarrabi zuri', 'xarel-lo', 'macabeo', 'grillo',
        'fiano', 'falanghina', 'müller-thurgau', 'muller-thurgau', 'petit chablis',
        'malagouzia', 'malagousia', 'savatiano', 'moschofilero', 'bacchus',
        'weissburgunder', 'weißburgunder',
    ];

    /** Appellations and grapes that are red by definition. */
    private const RED_BY_DEFINITION = [
        // Burgundy & Beaujolais crus. NOTE: 'chambertin' covers Gevrey- and
        // every -Chambertin grand cru; ordered AFTER the white list so
        // Corton-Charlemagne resolves white before 'corton' resolves red.
        'chambertin', 'chambolle-musigny', 'vosne-romanée', 'vosne-romanee',
        'nuits-st-georges', 'nuits-saint-georges', 'pommard', 'volnay',
        'morey-saint-denis', 'morey-st-denis', 'fixin', 'aloxe-corton',
        'clos de vougeot', 'richebourg', 'romanée-conti', 'romanee-conti',
        'la tâche', 'la tache', 'échezeaux', 'echezeaux', 'corton',
        'moulin-à-vent', 'moulin-a-vent', 'fleurie', 'morgon', 'brouilly',
        'juliénas', 'julienas', 'chénas', 'chenas', 'régnié', 'regnie', 'chiroubles', 'saint-amour',
        'passe-tout-grain', 'passetoutgrain', 'beaujolais',
        'spätburgunder', 'spatburgunder',
        // Italian/Spanish reds by definition
        'barolo', 'barbaresco', 'brunello', 'chianti', 'amarone', 'ripasso',
        'valpolicella', 'aglianico', 'sagrantino', 'nero d\'avola',
        // Red grapes
        'pinot noir', 'gamay', 'nebbiolo', 'sangiovese', 'tempranillo',
        'cabernet sauvignon', 'cabernet franc', 'merlot', 'syrah', 'shiraz',
        'malbec', 'mourvèdre', 'mourvedre', 'grenache noir', 'carignan',
        'zweigelt', 'blaufränkisch', 'blaufrankisch', 'mencía', 'mencia',
        'montepulciano d\'abruzzo',
    ];

    public static function infer(string $wineName, ?string $producer = null): ?WineColour
    {
        $haystack = mb_strtolower($wineName.' '.((string) $producer));

        // Order matters: style/method words outrank colour words ("Blanc de
        // Noirs" champagne is Sparkling), colour words outrank appellation
        // defaults ("Nuits-St-Georges Blanc" is White), and appellations are
        // only consulted when the name says nothing explicit.
        foreach ([
            [self::SPARKLING, WineColour::Sparkling],
            [self::FORTIFIED, WineColour::Fortified],
            [self::DESSERT, WineColour::Dessert],
            [self::ROSE_WORDS, WineColour::Rose],
            [self::WHITE_WORDS, WineColour::White],
            [self::RED_WORDS, WineColour::Red],
            [self::WHITE_BY_DEFINITION, WineColour::White],
            [self::RED_BY_DEFINITION, WineColour::Red],
        ] as [$markers, $colour]) {
            foreach ($markers as $marker) {
                if (preg_match('/\b'.preg_quote($marker, '/').'\b/u', $haystack) === 1) {
                    return $colour;
                }
            }
        }

        return null;
    }
}
