<?php

declare(strict_types=1);

namespace Domain\Catalogue\Support;

/**
 * Vocabulary marking a product as NOT a wine — distilled and non-grape drinks
 * that mixed trade lists carry alongside wine (spirits, sake, cider, mineral
 * water). Fortified WINES (port, sherry, madeira, vermouth) are deliberately
 * absent, and names like "liqueur muscat" / "vin de liqueur" are allowlisted:
 * they contain a marker word but are fortified wines.
 *
 * Used by the parse pipeline's vet step (flags rows `non_wine` for human
 * review) and by catalogue cleanups.
 */
class NonWineVocabulary
{
    private const MARKERS = [
        'armagnac', 'cognac', 'calvados', 'eau de vie', 'eaux de vie', 'brandy',
        'grappa', 'marc de', 'whisky', 'whiskey', 'bourbon', 'gin', 'vodka',
        'rum', 'tequila', 'mezcal', 'pastis', 'absinthe', 'liqueur',
        'sake', 'umeshu', 'yuzushu', 'junmai', 'ginjo', 'honjozo', 'daiginjo',
        'cider', 'sidre', 'sydre', 'perry', 'beer', 'lager',
        'still water', 'sparkling water', 'mineral water', 'eaux minerales', 'eaux minérales',
    ];

    /** Fortified-wine styles whose names contain a marker word anyway. */
    private const WINE_ALLOWLIST = '/\b(?:liqueur muscat|muscat liqueur|vin de liqueur)\b/u';

    public static function matches(string $wineName, ?string $producer = null): bool
    {
        $haystack = mb_strtolower($wineName.' '.((string) $producer));

        if (preg_match(self::WINE_ALLOWLIST, $haystack) === 1) {
            return false;
        }

        foreach (self::MARKERS as $marker) {
            if (preg_match('/\b'.preg_quote($marker, '/').'\b/u', $haystack) === 1) {
                return true;
            }
        }

        return false;
    }
}
