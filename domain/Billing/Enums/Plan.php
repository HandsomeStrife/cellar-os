<?php

declare(strict_types=1);

namespace Domain\Billing\Enums;

enum Plan: string
{
    case Pro = 'pro';
    case Group = 'group';

    /**
     * Plans that existed before the line-up collapsed to Pro + Group
     * (2026-08-09), mapped to what they became. Production rows and Stripe
     * subscriptions still carry these values, so every read path normalises
     * through {@see self::fromValue()} rather than a bare `from()`.
     *
     * @var array<string, string>
     */
    public const LEGACY = [
        'free' => 'pro',
        'starter' => 'pro',
    ];

    /**
     * The plan a company gets when nothing else is known — on registration,
     * when a subscription lapses, and as the fallback for an unresolved
     * company. Everything except multiple venues is included.
     */
    public static function default(): self
    {
        return self::Pro;
    }

    /**
     * Resolve a stored/legacy string to a live plan. Unknown values fall back
     * to the default rather than throwing: a plan we can't read should never
     * take the whole app down.
     */
    public static function fromValue(?string $value): self
    {
        if ($value === null || $value === '') {
            return self::default();
        }

        return self::tryFrom($value)
            ?? self::tryFrom(self::LEGACY[$value] ?? '')
            ?? self::default();
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Pro => 'Pro',
            self::Group => 'Group',
        };
    }

    /**
     * Rank within the upgrade ladder (pro < group).
     */
    public function rank(): int
    {
        return match ($this) {
            self::Pro => 0,
            self::Group => 1,
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
            self::Pro => '£79',
            self::Group => '£199',
        };
    }

    public function tagline(): string
    {
        return match ($this) {
            self::Pro => 'Everything one venue needs: suppliers, price lists, catalogue, orders and inventory.',
            self::Group => 'The same, across every venue in the group, with a team to match.',
        };
    }

    public function isPaid(): bool
    {
        return true;
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
        if ($priceId === '') {
            return null;
        }

        foreach (self::cases() as $plan) {
            if ($plan->stripePriceId() === $priceId) {
                return $plan;
            }
        }

        // A subscription still on a retired price id keeps working at the
        // tier that price mapped to.
        foreach (self::LEGACY as $legacy => $replacement) {
            if (config("billing.prices.{$legacy}") === $priceId) {
                return self::tryFrom($replacement);
            }
        }

        return null;
    }

    /**
     * Paid plans, in upgrade order. Every plan is paid.
     *
     * @return array<int, self>
     */
    public static function paid(): array
    {
        return self::cases();
    }
}
