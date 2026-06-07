<?php

declare(strict_types=1);

namespace Domain\Billing\Enums;

enum Plan: string
{
    case Free = 'free';
    case Starter = 'starter';
    case Pro = 'pro';
    case Group = 'group';

    public function getLabel(): string
    {
        return match ($this) {
            self::Free => 'Free',
            self::Starter => 'Starter',
            self::Pro => 'Pro',
            self::Group => 'Group',
        };
    }

    /**
     * Rank within the upgrade ladder (free < starter < pro < group).
     */
    public function rank(): int
    {
        return match ($this) {
            self::Free => 0,
            self::Starter => 1,
            self::Pro => 2,
            self::Group => 3,
        };
    }

    public function atLeast(self $required): bool
    {
        return $this->rank() >= $required->rank();
    }

    public function can(Feature $feature): bool
    {
        return $this->atLeast($feature->minPlan());
    }

    /**
     * Headline monthly price (display only; real amounts live in Stripe).
     */
    public function monthlyPrice(): string
    {
        return match ($this) {
            self::Free => '£0',
            self::Starter => '£29',
            self::Pro => '£79',
            self::Group => '£199',
        };
    }

    public function tagline(): string
    {
        return match ($this) {
            self::Free => 'Browse the catalogue and manage suppliers.',
            self::Starter => 'Import price lists, run inventory and raise purchase orders.',
            self::Pro => 'Manual inventory, archiving and invoice attachments.',
            self::Group => 'Everything, across multiple venues.',
        };
    }

    public function isPaid(): bool
    {
        return $this !== self::Free;
    }

    /**
     * Stripe price id for this plan, from config/billing.php (null if unset).
     */
    public function stripePriceId(): ?string
    {
        return config("billing.prices.{$this->value}");
    }

    public static function forStripePrice(string $priceId): ?self
    {
        foreach (self::cases() as $plan) {
            if ($plan->stripePriceId() === $priceId && $priceId !== '') {
                return $plan;
            }
        }

        return null;
    }

    /**
     * Paid plans, in upgrade order.
     *
     * @return array<int, self>
     */
    public static function paid(): array
    {
        return [self::Starter, self::Pro, self::Group];
    }
}
