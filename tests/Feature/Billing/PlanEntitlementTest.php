<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Domain\Billing\Enums\BillingArrangement;
use Domain\Billing\Enums\Plan;
use Domain\Billing\Support\PlanEntitlement;

it('leaves a company with no trial exactly as it was', function () {
    // The safety property that makes this shippable over live data: every
    // existing company has a null trial_ends_at and must be untouched.
    expect(PlanEntitlement::resolve(Plan::Group))->toBe(Plan::Group)
        ->and(PlanEntitlement::resolve(Plan::Pro))->toBe(Plan::Pro);
});

it('keeps the plan while a trial is still running', function () {
    expect(PlanEntitlement::resolve(
        plan: Plan::Group,
        trialEndsAt: CarbonImmutable::now()->addDay(),
    ))->toBe(Plan::Group);
});

it('drops to the entry tier when a trial ends with nothing behind it', function () {
    expect(PlanEntitlement::resolve(
        plan: Plan::Group,
        trialEndsAt: CarbonImmutable::now()->subSecond(),
        hasActiveSubscription: false,
    ))->toBe(Plan::default());
});

it('does NOT downgrade when we cannot establish whether they are paying', function () {
    // Null means "not established", not "no subscription". Only fromModel()
    // can answer it, so a hand-built DTO must never be a downgrade switch:
    // wrongly demoting a paying customer is far worse than carrying a lapsed
    // trial one more day, and only one of the two arrives as an angry email.
    expect(PlanEntitlement::resolve(
        plan: Plan::Group,
        trialEndsAt: CarbonImmutable::now()->subMonth(),
        hasActiveSubscription: null,
    ))->toBe(Plan::Group);
});

it('keeps the plan when a paid subscription took over from the trial', function () {
    expect(PlanEntitlement::resolve(
        plan: Plan::Group,
        trialEndsAt: CarbonImmutable::now()->subMonth(),
        hasActiveSubscription: true,
    ))->toBe(Plan::Group);
});

it('never downgrades a comped company, whatever its trial says', function () {
    // We are not going to bill them, so there is nothing for them to fail to
    // pay. A stale trial date must not quietly take a partner's venues away.
    expect(PlanEntitlement::resolve(
        plan: Plan::Group,
        arrangement: BillingArrangement::Comped,
        trialEndsAt: CarbonImmutable::now()->subYear(),
    ))->toBe(Plan::Group);
});

it('never downgrades a company on an agreed price either', function () {
    // A negotiated price is not in the Stripe catalogue — the checkout only
    // ever offers a plan's list price — so a custom-price customer could NEVER
    // satisfy a subscription check however diligently they paid us.
    // Downgrading them would punish them for how we chose to invoice.
    expect(PlanEntitlement::resolve(
        plan: Plan::Group,
        arrangement: BillingArrangement::Custom,
        trialEndsAt: CarbonImmutable::now()->subDay(),
        hasActiveSubscription: false,
    ))->toBe(Plan::Group);
});

it('separates "they never paid" from "they lost something"', function () {
    $lapsed = CarbonImmutable::now()->subDay();

    // On Group, a lapse costs them the tier.
    expect(PlanEntitlement::hasLapsed(BillingArrangement::Standard, $lapsed, false))->toBeTrue()
        ->and(PlanEntitlement::reducesEntitlement(Plan::Group, BillingArrangement::Standard, $lapsed, false))->toBeTrue();

    // On the ENTRY tier it costs them nothing — there is nothing below it —
    // but they are still someone who trialled and never paid, which is the
    // fact the back office needs.
    expect(PlanEntitlement::hasLapsed(BillingArrangement::Standard, $lapsed, false))->toBeTrue()
        ->and(PlanEntitlement::reducesEntitlement(Plan::default(), BillingArrangement::Standard, $lapsed, false))->toBeFalse();

    // Comped and paying companies haven't lapsed at all.
    expect(PlanEntitlement::hasLapsed(BillingArrangement::Comped, $lapsed, false))->toBeFalse()
        ->and(PlanEntitlement::hasLapsed(BillingArrangement::Standard, $lapsed, true))->toBeFalse()
        ->and(PlanEntitlement::hasLapsed(BillingArrangement::Standard, null, false))->toBeFalse();
});

it('expires a trial at the moment it ends, not a day later', function () {
    $endsAt = CarbonImmutable::parse('2026-09-01 23:59:59');

    expect(PlanEntitlement::onTrial($endsAt, $endsAt->subSecond()))->toBeTrue()
        ->and(PlanEntitlement::onTrial($endsAt, $endsAt))->toBeFalse()
        ->and(PlanEntitlement::onTrial($endsAt, $endsAt->addSecond()))->toBeFalse();
});

it('counts the days left of a trial in whole days', function () {
    $now = CarbonImmutable::parse('2026-08-01 09:00:00');

    // Any time left at all reads as at least a day: "0 days left" while the
    // trial still works would be a lie. Otherwise it counts down, so two days
    // and an hour is "2 days", not 3.
    expect(PlanEntitlement::trialDaysRemaining($now->addHours(2), $now))->toBe(1)
        ->and(PlanEntitlement::trialDaysRemaining($now->addDays(30), $now))->toBe(30)
        ->and(PlanEntitlement::trialDaysRemaining($now->addDays(2)->addHour(), $now))->toBe(2)
        ->and(PlanEntitlement::trialDaysRemaining($now->subDay(), $now))->toBe(0)
        ->and(PlanEntitlement::trialDaysRemaining(null, $now))->toBeNull();
});

it('reads a trial granted today as exactly the number of days asked for', function () {
    // The admin grants "30 days", which lands at the END of the 30th day.
    // The badge must still say 30, whatever time of day it was granted.
    foreach (['00:01', '09:00', '23:58'] as $time) {
        $now = CarbonImmutable::parse("2026-08-01 {$time}");
        $endsAt = $now->addDays(30)->endOfDay();

        expect(PlanEntitlement::trialDaysRemaining($endsAt, $now))->toBe(30);
    }
});
