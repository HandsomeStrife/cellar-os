<?php

declare(strict_types=1);

namespace Domain\Catalogue\Enums;

/**
 * Why a wine has no price.
 *
 * CellarOS does not carry catalogue data without a price — but there is a real
 * difference between a price we FAILED to read and a price the supplier
 * deliberately withheld. Les Caves de Pyrene, for instance, lists growers whose
 * allocations are so small they print prose instead of a figure:
 *
 *   "We receive tiny allocations from this grower so rarely have bottles in
 *    stock but please ask your rep… about availability"
 *
 * That wine genuinely belongs in the catalogue — the buyer needs to know it
 * exists and who to ask. So a stated POA/TBC is a first-class state that
 * survives `wine:archive-priceless`, while a blank price is still treated as
 * missing data and archived.
 */
enum PriceState: string
{
    case Priced = 'priced';
    case Poa = 'poa';
    case Tbc = 'tbc';

    public function getLabel(): string
    {
        return match ($this) {
            self::Priced => 'Priced',
            self::Poa => 'POA',
            self::Tbc => 'TBC',
        };
    }

    /**
     * Longer wording for tooltips and the wine detail panel.
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::Priced => 'Priced',
            self::Poa => 'Price on application — ask the supplier for a quote.',
            self::Tbc => 'Price to be confirmed by the supplier.',
        };
    }

    /**
     * Whether a wine in this state is expected to carry a figure.
     */
    public function expectsPrice(): bool
    {
        return $this === self::Priced;
    }

    /**
     * Detect a supplier's own "ask us" wording in a price cell.
     *
     * Only ever consulted when the cell has no parseable number, and matched on
     * whole words so a wine called "Poachers Rest" isn't mistaken for a quote.
     */
    public static function fromPriceText(?string $text): ?self
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        $haystack = mb_strtolower($text);

        foreach ([
            [self::Poa, [
                'poa', 'p\.o\.a', 'price on application', 'price on request',
                'on application', 'on request', 'ask your rep', 'ask us',
                'please ask', 'contact us', 'enquire', 'inquire', 'allocation',
            ]],
            [self::Tbc, ['tbc', 't\.b\.c', 'to be confirmed', 'tba', 'to be advised']],
        ] as [$state, $markers]) {
            foreach ($markers as $marker) {
                if (preg_match('/\b'.$marker.'\b/u', $haystack) === 1) {
                    return $state;
                }
            }
        }

        return null;
    }
}
