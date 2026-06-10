<?php

declare(strict_types=1);

namespace Domain\Catalogue\Support;

use Illuminate\Support\Str;

/**
 * Computes the cross-supplier identity of a wine: a normalised producer+name
 * key ("Château PALMER" ≡ "Chateau Palmer"). Vintage and format are EXCLUDED —
 * the facts this keys (grape, colour, origin) are stable across vintages.
 *
 * A producer is REQUIRED: matching on wine name alone would merge every
 * supplier's "Chablis" into one identity and cross-contaminate facts.
 */
class WineIdentity
{
    /** Placeholder "producers" that would merge unrelated wines into one identity. */
    private const PLACEHOLDER_PRODUCERS = ['n a', 'na', 'n k', 'various', 'unknown', 'tbc', 'tba', 'misc', 'miscellaneous', 'none'];

    public static function keyFor(?string $producer, ?string $wineName): ?string
    {
        $producer = trim((string) $producer);
        $wineName = trim((string) $wineName);

        if ($producer === '' || $wineName === '') {
            return null;
        }

        $producerKey = preg_replace('/\s+/', ' ', trim(preg_replace('/[^a-z0-9 ]+/', ' ', Str::ascii(mb_strtolower($producer))) ?? ''));

        if ($producerKey === '' || in_array($producerKey, self::PLACEHOLDER_PRODUCERS, true)) {
            return null;
        }

        $key = Str::ascii(mb_strtolower($producer.' '.$wineName));
        $key = preg_replace('/[^a-z0-9 ]+/', ' ', $key) ?? '';
        $key = preg_replace('/\s+/', ' ', trim($key)) ?? '';

        return $key === '' ? null : mb_substr($key, 0, 250);
    }
}
