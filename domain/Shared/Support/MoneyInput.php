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

        // Scientific notation would otherwise survive as digits: "1e3" reads
        // as 13, quietly charging £13 for a typed 1000.
        if (preg_match('/[eE]/', $input) === 1) {
            return null;
        }

        $cleaned = preg_replace('/[^0-9.,\-]/', '', $input) ?? '';

        if ($cleaned === '') {
            return null;
        }

        // A comma is a decimal separator in half of Europe and a thousands
        // separator in the other half. With at most two digits after it, read
        // it as a decimal point; otherwise it's grouping and can go.
        if (preg_match('/,\d{1,2}$/', $cleaned) === 1) {
            $cleaned = str_replace(['.', ','], ['', '.'], $cleaned);
        } else {
            $cleaned = str_replace(',', '', $cleaned);
        }

        if (! is_numeric($cleaned)) {
            return null;
        }

        $minorUnits = (int) round((float) $cleaned * 100);

        // A negative price is never what someone meant, and the column it
        // lands in is unsigned. A number past the ceiling would overflow it.
        if ($minorUnits < 0 || $minorUnits > self::MAX_MINOR_UNITS) {
            return null;
        }

        return $minorUnits;
    }

    /**
     * Minor units back to a plain editable string ("4950" → "49.50").
     */
    public static function toDecimalString(?int $minorUnits): string
    {
        return $minorUnits === null ? '' : number_format($minorUnits / 100, 2, '.', '');
    }
}
