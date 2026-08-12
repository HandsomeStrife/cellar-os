<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Domain\Billing\Enums\BillingArrangement;
use Domain\Billing\Enums\BillingInterval;
use Domain\Billing\Enums\Plan;
use Domain\Company\Data\CompanyBillingData;

/**
 * `normalised()` is the domain's guard against a company carrying a price its
 * arrangement doesn't use. The admin forms happen to null those fields too, so
 * without these tests the guard could be deleted and every other test would
 * still pass — leaving a CLI or API caller unprotected.
 */
function terms(BillingArrangement $arrangement): CompanyBillingData
{
    return new CompanyBillingData(
        plan: Plan::Group,
        billing_arrangement: $arrangement,
        custom_price_amount: 4900,
        custom_price_currency: 'GBP',
        custom_price_interval: BillingInterval::Month,
        billing_notes: 'Agreed on a call.',
    );
}

it('strips a price from an arrangement that does not use one', function () {
    foreach ([BillingArrangement::Standard, BillingArrangement::Comped] as $arrangement) {
        $normalised = terms($arrangement)->normalised();

        expect($normalised->custom_price_amount)->toBeNull()
            ->and($normalised->custom_price_currency)->toBeNull()
            ->and($normalised->custom_price_interval)->toBeNull()
            // The note survives: why we comped someone still matters.
            ->and($normalised->billing_notes)->toBe('Agreed on a call.')
            ->and($normalised->plan)->toBe(Plan::Group);
    }
});

it('leaves the price alone on a custom arrangement', function () {
    $normalised = terms(BillingArrangement::Custom)->normalised();

    expect($normalised->custom_price_amount)->toBe(4900)
        ->and($normalised->custom_price_currency)->toBe('GBP')
        ->and($normalised->custom_price_interval)->toBe(BillingInterval::Month);
});

it('carries the trial through untouched', function () {
    $endsAt = CarbonImmutable::parse('2026-09-30 23:59:59');

    $normalised = new CompanyBillingData(
        plan: Plan::Pro,
        billing_arrangement: BillingArrangement::Standard,
        custom_price_amount: 999,
        trial_ends_at: $endsAt,
    );

    expect($normalised->normalised()->trial_ends_at)->toEqual($endsAt);
});
