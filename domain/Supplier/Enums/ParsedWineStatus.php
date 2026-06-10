<?php

declare(strict_types=1);

namespace Domain\Supplier\Enums;

enum ParsedWineStatus: string
{
    case Proposed = 'proposed';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Proposed => 'Proposed',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    public function getColour(): string
    {
        return match ($this) {
            self::Proposed => 'amber',
            self::Approved => 'green',
            self::Rejected => 'gray',
        };
    }
}
