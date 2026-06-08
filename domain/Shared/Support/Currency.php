<?php

declare(strict_types=1);

namespace Domain\Shared\Support;

/**
 * Currency display helpers. CellarOS stores monetary values as-is per venue
 * base currency (and snapshots currency per order/inventory line) — there is
 * no conversion, mirroring the upstream.
 */
class Currency
{
    /** @var array<string, string> */
    public const SYMBOLS = [
        'GBP' => '£',
        'EUR' => '€',
        'USD' => '$',
    ];

    public static function symbol(?string $code): string
    {
        return self::SYMBOLS[strtoupper((string) $code)] ?? '£';
    }

    public static function format(float|string|null $amount, ?string $code = 'GBP'): string
    {
        return self::symbol($code).number_format((float) $amount, 2);
    }
}
