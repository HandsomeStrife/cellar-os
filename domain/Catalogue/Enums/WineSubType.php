<?php

declare(strict_types=1);

namespace Domain\Catalogue\Enums;

/**
 * An optional narrowing of {@see WineType}.
 *
 * A wine always has a type; a sub-type only exists where the trade genuinely
 * distinguishes within one — a sparkling rosé and a sparkling white are both
 * Sparkling and must both come back when you filter for Sparkling, but a
 * sommelier building a list needs to tell them apart. Same for the fortified
 * family, which is really four unrelated drinks under one heading.
 *
 * Deliberately NOT modelled here: sub-types for still red/white/rosé. There is
 * no agreed trade vocabulary below "red" that isn't really grape, region or
 * body — all of which we already carry as their own columns.
 */
enum WineSubType: string
{
    // Sparkling
    case SparklingWhite = 'Sparkling White';
    case SparklingRose = 'Sparkling Rosé';
    case SparklingRed = 'Sparkling Red';
    case PetNat = 'Pét-Nat';

    // Fortified
    case Port = 'Port';
    case Sherry = 'Sherry';
    case Madeira = 'Madeira';
    case Vermouth = 'Vermouth';

    /**
     * The type this sub-type lives under. Filtering by the parent always
     * includes every one of its sub-types.
     */
    public function parent(): WineType
    {
        return match ($this) {
            self::SparklingWhite,
            self::SparklingRose,
            self::SparklingRed,
            self::PetNat => WineType::Sparkling,

            self::Port,
            self::Sherry,
            self::Madeira,
            self::Vermouth => WineType::Fortified,
        };
    }

    public function getLabel(): string
    {
        return $this->value;
    }

    /**
     * The label without its parent's name, for use in a column that already
     * shows the type ("Sparkling · Rosé" rather than "Sparkling · Sparkling
     * Rosé").
     */
    public function getShortLabel(): string
    {
        return trim(str_replace($this->parent()->value, '', $this->value)) ?: $this->value;
    }

    /**
     * Sub-types available under a type, in display order.
     *
     * @return array<int, self>
     */
    public static function forType(WineType $type): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $subType) => $subType->parent() === $type,
        ));
    }

    /**
     * Every type that has sub-types worth offering.
     *
     * @return array<int, WineType>
     */
    public static function typesWithSubTypes(): array
    {
        $types = [];

        foreach (self::cases() as $subType) {
            $types[$subType->parent()->value] = $subType->parent();
        }

        return array_values($types);
    }
}
