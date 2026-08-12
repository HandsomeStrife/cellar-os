<?php

declare(strict_types=1);

namespace Domain\Billing\Enums;

/**
 * How a company pays, which is a separate question from what it can DO.
 *
 * The plan ({@see Plan}) is the entitlement ladder and decides which features
 * are unlocked. The arrangement decides what we charge for that plan, so a
 * partner on Group for nothing and a customer on Group at list price have the
 * same capabilities and different invoices.
 *
 * Keeping the two apart is what makes a discount or a comp a back-office
 * decision rather than a code change.
 */
enum BillingArrangement: string
{
    /** List price for the plan, billed through Stripe as normal. */
    case Standard = 'standard';

    /** Free, indefinitely — partners, friends of the house, our own accounts. */
    case Comped = 'comped';

    /** A negotiated price we hold on the company record. */
    case Custom = 'custom';

    public function getLabel(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::Comped => 'Free',
            self::Custom => 'Custom price',
        };
    }

    /**
     * Badge colour token (see x-badge).
     */
    public function getColour(): string
    {
        return match ($this) {
            self::Standard => 'gray',
            self::Comped => 'emerald',
            self::Custom => 'blue',
        };
    }

    /**
     * Does this arrangement mean we never chase them for money? A comped
     * company is never downgraded for want of a subscription.
     */
    public function isFree(): bool
    {
        return $this === self::Comped;
    }

    /**
     * Only a custom arrangement carries an agreed amount; the others take
     * their price from the plan (or from nothing at all).
     */
    public function needsPrice(): bool
    {
        return $this === self::Custom;
    }

    /**
     * @return array<string, string> value => label, for select options
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])
            ->all();
    }
}
