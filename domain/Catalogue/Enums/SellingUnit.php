<?php

declare(strict_types=1);

namespace Domain\Catalogue\Enums;

/**
 * How a supplier quotes (and is ordered from) for a given wine. Some lists are
 * priced per single bottle, others by the case. The catalogue stores a
 * canonical per-bottle `unit_price` for cross-supplier comparison regardless,
 * but display and (later) ordering respect the supplier's native unit.
 */
enum SellingUnit: string
{
    case Bottle = 'bottle';
    case Case = 'case';

    public function getLabel(): string
    {
        return match ($this) {
            self::Bottle => 'Bottle',
            self::Case => 'Case',
        };
    }

    /**
     * Tolerant parse for imported/restored values; unknown/empty → Bottle.
     */
    public static function parse(int|string|null $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return self::tryFrom(strtolower(trim((string) $value))) ?? self::Bottle;
    }
}
