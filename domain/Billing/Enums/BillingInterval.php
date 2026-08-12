<?php

declare(strict_types=1);

namespace Domain\Billing\Enums;

/**
 * How often a custom price recurs. Deliberately only the two Stripe bills on
 * by default — anything stranger than monthly or yearly is a conversation, not
 * a dropdown.
 */
enum BillingInterval: string
{
    case Month = 'month';
    case Year = 'year';

    public function getLabel(): string
    {
        return match ($this) {
            self::Month => 'a month',
            self::Year => 'a year',
        };
    }

    public function getShortLabel(): string
    {
        return match ($this) {
            self::Month => 'Monthly',
            self::Year => 'Yearly',
        };
    }

    /**
     * @return array<string, string> value => label, for select options
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->getShortLabel()])
            ->all();
    }
}
