<?php

declare(strict_types=1);

namespace Domain\Catalogue\Enums;

enum WineColour: string
{
    case Red = 'Red';
    case White = 'White';
    case Rose = 'Rosé';
    case Orange = 'Orange';
    case Sparkling = 'Sparkling';
    case Dessert = 'Dessert';
    case Fortified = 'Fortified';

    public function getLabel(): string
    {
        return $this->value;
    }

    /**
     * Position in the trade's conventional list order (sparkling first,
     * fortified and other oddities last). Drives the catalogue's default sort.
     */
    public function getSortOrder(): int
    {
        return match ($this) {
            self::Sparkling => 1,
            self::White => 2,
            self::Rose => 3,
            self::Orange => 4,
            self::Red => 5,
            self::Dessert => 6,
            self::Fortified => 7,
        };
    }

    /**
     * Hex swatch used in the catalogue/inventory UI.
     */
    public function getSwatch(): string
    {
        return match ($this) {
            self::Red => '#7b1e3b',
            self::White => '#e9dca6',
            self::Rose => '#e8a0a8',
            self::Orange => '#d98e3b',
            self::Sparkling => '#f2e9c9',
            self::Dessert => '#c8852b',
            self::Fortified => '#5c2018',
        };
    }
}
