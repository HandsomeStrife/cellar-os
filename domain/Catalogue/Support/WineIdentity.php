<?php

declare(strict_types=1);

namespace Domain\Catalogue\Support;

use Illuminate\Support\Str;

/**
 * Computes the cross-supplier identity of a wine: a normalised producer|name
 * key ("Château PALMER" ≡ "Chateau Palmer"). Vintage and format are EXCLUDED —
 * the facts this keys (grape, colour, origin) are stable across vintages.
 *
 * A producer is REQUIRED: matching on wine name alone would merge every
 * supplier's "Chablis" into one identity and cross-contaminate facts. The
 * producer and name segments are joined with a separator that survives
 * normalisation, so ("Château", "Margaux Rouge") and ("Château Margaux",
 * "Rouge") stay distinct wines.
 */
class WineIdentity
{
    /** Placeholder "producers" that would merge unrelated wines into one identity. */
    private const PLACEHOLDER_PRODUCERS = ['n a', 'na', 'n k', 'various', 'unknown', 'tbc', 'tba', 'misc', 'miscellaneous', 'none'];

    public static function keyFor(?string $producer, ?string $wineName): ?string
    {
        $producerKey = self::normalise($producer);
        $nameKey = self::normalise($wineName);

        if ($producerKey === '' || $nameKey === '' || in_array($producerKey, self::PLACEHOLDER_PRODUCERS, true)) {
            return null;
        }

        return mb_substr($producerKey.'|'.$nameKey, 0, 250);
    }

    /**
     * Accent-folded, lowercased, punctuation-stripped text — used for identity
     * keys AND for value-equality checks (so "Côtes du Rhône" agrees with
     * "Cotes du Rhone" instead of flagging a conflict).
     */
    public static function normalise(?string $value): string
    {
        $value = Str::ascii(mb_strtolower(trim((string) $value)));
        $value = preg_replace('/[^a-z0-9 ]+/', ' ', $value) ?? '';

        return preg_replace('/\s+/', ' ', trim($value)) ?? '';
    }
}
