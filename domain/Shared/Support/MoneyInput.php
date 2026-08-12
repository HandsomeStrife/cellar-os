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
     * @return int|null Null for an empty input, so "no price" stays distinct
     *                  from "zero".
     */
    public static function toMinorUnits(?string $input): ?int
    {
        if ($input === null) {
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

        return (int) round((float) $cleaned * 100);
    }

    /**
     * Minor units back to a plain editable string ("4950" → "49.50").
     */
    public static function toDecimalString(?int $minorUnits): string
    {
        return $minorUnits === null ? '' : number_format($minorUnits / 100, 2, '.', '');
    }
}
