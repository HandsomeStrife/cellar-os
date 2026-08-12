<?php

declare(strict_types=1);

namespace Domain\Shared\Support;

/**
 * Turning what a person typed into an integer number of minor units.
 *
 * Prices are stored as integers so no amount is ever a float, but people type
 * "49.50", "1,299", or "£49.50" with a stray symbol. Rounding rather than
 * truncating matters: (int) (49.50 * 100) is 4949 on a binary float, which
 * would quietly undercharge by a penny.
 */
class MoneyInput
{
    /**
     * Ceiling in minor units (£10,000,000). Well inside the unsignedInteger
     * column it lands in, and far beyond any real wine-trade subscription —
     * a number above this is a typo, not a deal.
     */
    public const MAX_MINOR_UNITS = 1_000_000_000;

    /**
     * @return int|null Null for an empty input, so "no price" stays distinct
     *                  from "zero"; also null for anything that isn't a
     *                  plausible amount. The safety belongs to the money type,
     *                  not to whichever form happens to call it.
     */
    public static function toMinorUnits(?string $input): ?int
    {
        if ($input === null) {
            return null;
        }

        // Take off a currency symbol and any surrounding space, then insist
        // the REST already looks like an amount. Stripping stray characters
        // first and asking questions later turns "4 9" into £49, "(50)" into
        // £50 (an accounting negative, inverted) and "0x1A" into £1.
        $trimmed = trim(preg_replace('/^\s*[£$€]\s*/u', '', trim($input)) ?? '');

        // Accepted shapes, each of them unambiguous:
        //   49, 49.50          plain, dot decimal
        //   1,299, 1,299.50    comma grouping
        //   49,50              comma decimal
        //   1.299,50           dot grouping, settled by the comma decimal
        //   1.234.567          dot grouping, two groups or more
        //
        // Deliberately NOT accepted: a bare "1.234", which is either 1234
        // grouped the European way or an amount with three decimals. Guessing
        // is wrong half the time and the admin never sees the figure we
        // guessed at, so refuse it and let them retype.
        $shapes = [
            '/^\d+(?:\.\d{1,2})?$/',
            '/^\d{1,3}(?:,\d{3})+(?:\.\d{1,2})?$/',
            '/^\d+,\d{1,2}$/',
            '/^\d{1,3}(?:\.\d{3})+,\d{1,2}$/',
            '/^\d{1,3}(?:\.\d{3}){2,}$/',
        ];

        $recognised = false;

        foreach ($shapes as $shape) {
            if (preg_match($shape, $trimmed) === 1) {
                $recognised = true;
                break;
            }
        }

        if (! $recognised) {
            return null;
        }

        // A trailing comma with one or two digits is a decimal point; anything
        // else that's left is grouping, and can go.
        $cleaned = preg_match('/,\d{1,2}$/', $trimmed) === 1
            ? str_replace(['.', ','], ['', '.'], $trimmed)
            : str_replace(',', '', $trimmed);

        // Dots that survived are grouping too, unless they introduce the
        // decimal part.
        if (preg_match('/\.\d{1,2}$/', $cleaned) !== 1) {
            $cleaned = str_replace('.', '', $cleaned);
        }

        if (! is_numeric($cleaned)) {
            return null;
        }

        // Bound it as a FLOAT, before any cast. Casting first means PHP warns
        // that a huge float isn't representable as an int — and Laravel turns
        // that warning into a thrown exception, so a long pasted number would
        // 500 the form instead of failing validation politely.
        $amount = (float) $cleaned;

        if ($amount < 0 || $amount > self::MAX_MINOR_UNITS / 100) {
            return null;
        }

        return (int) round($amount * 100);
    }

    /**
     * Minor units back to a plain editable string ("4950" → "49.50").
     */
    public static function toDecimalString(?int $minorUnits): string
    {
        return $minorUnits === null ? '' : number_format($minorUnits / 100, 2, '.', '');
    }
}
