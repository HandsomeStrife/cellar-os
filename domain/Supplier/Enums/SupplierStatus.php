<?php

declare(strict_types=1);

namespace Domain\Supplier\Enums;

enum SupplierStatus: string
{
    case Active = 'Active';
    case Inactive = 'Inactive';

    public function getLabel(): string
    {
        return $this->value;
    }

    /**
     * Badge colour token (see x-badge).
     */
    public function getColour(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Inactive => 'gray',
        };
    }

    /**
     * @return array<string, string> value => label, for select options
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status) => [$status->value => $status->getLabel()])
            ->all();
    }
}
