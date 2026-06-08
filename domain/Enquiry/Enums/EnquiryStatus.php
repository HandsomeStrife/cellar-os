<?php

declare(strict_types=1);

namespace Domain\Enquiry\Enums;

enum EnquiryStatus: string
{
    case New = 'new';
    case Read = 'read';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Read => 'Read',
            self::Archived => 'Archived',
        };
    }

    /** Maps to the x-badge `color` prop. */
    public function color(): string
    {
        return match ($this) {
            self::New => 'amber',
            self::Read => 'blue',
            self::Archived => 'gray',
        };
    }
}
