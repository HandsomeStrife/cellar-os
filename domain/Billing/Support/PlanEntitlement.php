<?php

declare(strict_types=1);

namespace Domain\Billing\Support;

use Carbon\CarbonImmutable;
use Domain\Billing\Enums\BillingArrangement;
use Domain\Billing\Enums\Plan;

/**
 * What a company is actually entitled to right now.
 *
 * `companies.plan` records the tier someone has been PUT on; this decides
 * whether they still hold it. The only thing that can take a tier away is a
 * trial running out with nothing to replace it — a company that has never been
 * given a trial keeps its plan exactly as before, which is what makes this safe
 * to introduce over live data.
 *
 * Everything here fails SAFE: where we are unsure, the company keeps what it
 * has. Wrongly downgrading someone who is paying us is a far worse failure than
 * carrying a lapsed trial for another day, and the second is visible in the
 * back office while the first shows up as an angry email.
 */
class PlanEntitlement
{
    /**
     * @param  bool|null  $hasActiveSubscription  Null means "not established".
     *                                            Treated as no reason to downgrade.
     */
    public static function resolve(
        Plan $plan,
        BillingArrangement $arrangement = BillingArrangement::Standard,
        ?CarbonImmutable $trialEndsAt = null,
        ?bool $hasActiveSubscription = null,
        ?CarbonImmutable $now = null,
    ): Plan {
        // Comped and custom-price companies don't hang off a Stripe record.
        if (! $arrangement->dependsOnStripe()) {
            return $plan;
        }

        // Never given a trial, or still inside one.
        if ($trialEndsAt === null || ! self::hasExpired($trialEndsAt, $now)) {
            return $plan;
        }

        // The trial is over. A real subscription carries them; so does not
        // knowing either way. Otherwise they fall back to the entry tier
        // rather than losing the app entirely.
        return $hasActiveSubscription === false ? Plan::default() : $plan;
    }

    /**
     * Has a trial been given, run out, and nothing taken its place?
     *
     * This is the commercial signal — "they had a go and never started paying"
     * — and it is TRUE whether or not the lapse actually cost them anything.
     * A trial of the entry tier revokes nothing (there is nothing below it),
     * so {@see self::reducesEntitlement()} is what says whether access changed;
     * this says whether we should be talking to them.
     */
    public static function hasLapsed(
        BillingArrangement $arrangement,
        ?CarbonImmutable $trialEndsAt,
        ?bool $hasActiveSubscription,
        ?CarbonImmutable $now = null,
    ): bool {
        return $arrangement->dependsOnStripe()
            && $trialEndsAt !== null
            && self::hasExpired($trialEndsAt, $now)
            && $hasActiveSubscription === false;
    }

    /**
     * Did the lapse actually take something away? False for a trial issued on
     * the entry tier, where there is nothing to fall back to.
     */
    public static function reducesEntitlement(
        Plan $plan,
        BillingArrangement $arrangement,
        ?CarbonImmutable $trialEndsAt,
        ?bool $hasActiveSubscription,
        ?CarbonImmutable $now = null,
    ): bool {
        return self::resolve($plan, $arrangement, $trialEndsAt, $hasActiveSubscription, $now) !== $plan;
    }

    /**
     * Is the company inside a trial window right now?
     */
    public static function onTrial(?CarbonImmutable $trialEndsAt, ?CarbonImmutable $now = null): bool
    {
        return $trialEndsAt !== null && ! self::hasExpired($trialEndsAt, $now);
    }

    /**
     * Whole days left of the trial. Null when there is no trial; 0 once it's
     * over.
     *
     * Counted DOWN to whole days, so a 30-day trial granted this morning reads
     * as "30 days" rather than 31 (it ends at the end of that day, which is a
     * few hours more than 30 × 24). With a floor of 1 while the trial is still
     * live, because "0 days left" on something that still works is a lie.
     */
    public static function trialDaysRemaining(?CarbonImmutable $trialEndsAt, ?CarbonImmutable $now = null): ?int
    {
        if ($trialEndsAt === null) {
            return null;
        }

        $now ??= CarbonImmutable::now();

        if (self::hasExpired($trialEndsAt, $now)) {
            return 0;
        }

        return max(1, (int) floor($now->diffInHours($trialEndsAt, false) / 24));
    }

    private static function hasExpired(CarbonImmutable $trialEndsAt, ?CarbonImmutable $now): bool
    {
        return $trialEndsAt->lessThanOrEqualTo($now ?? CarbonImmutable::now());
    }
}
