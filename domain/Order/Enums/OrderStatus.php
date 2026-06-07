<?php

declare(strict_types=1);

namespace Domain\Order\Enums;

enum OrderStatus: string
{
    case Draft = 'Draft';
    case Pending = 'Pending';
    case Sent = 'Sent';
    case Delivered = 'Delivered';
    case Cancelled = 'Cancelled';
    case Received = 'Received';

    public function getLabel(): string
    {
        return $this->value;
    }

    /**
     * Tailwind colour token (badge styling) for this status.
     */
    public function getColour(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Pending => 'amber',
            self::Sent => 'blue',
            self::Delivered => 'green',
            self::Received => 'emerald',
            self::Cancelled => 'red',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::Pending, self::Sent], true);
    }
}
