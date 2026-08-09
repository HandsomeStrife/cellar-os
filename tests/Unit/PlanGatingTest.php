<?php

declare(strict_types=1);

use Domain\Billing\Enums\Feature;
use Domain\Billing\Enums\Plan;

it('offers exactly two plans', function () {
    expect(Plan::cases())->toBe([Plan::Pro, Plan::Group]);
});

it('orders plans by rank', function () {
    expect(Plan::Pro->atLeast(Plan::Pro))->toBeTrue()
        ->and(Plan::Group->atLeast(Plan::Pro))->toBeTrue()
        ->and(Plan::Pro->atLeast(Plan::Group))->toBeFalse();
});

it('gives Pro the whole day-to-day product', function () {
    foreach (Feature::cases() as $feature) {
        if ($feature === Feature::MultiVenue) {
            continue;
        }

        expect(Plan::Pro->can($feature))->toBeTrue();
    }
});

it('reserves multiple venues for Group', function () {
    expect(Plan::Pro->can(Feature::MultiVenue))->toBeFalse()
        ->and(Plan::Group->can(Feature::MultiVenue))->toBeTrue();
});

it('resolves retired plan values to Pro', function () {
    expect(Plan::fromValue('free'))->toBe(Plan::Pro)
        ->and(Plan::fromValue('starter'))->toBe(Plan::Pro)
        ->and(Plan::fromValue('group'))->toBe(Plan::Group)
        ->and(Plan::fromValue(null))->toBe(Plan::Pro)
        ->and(Plan::fromValue('nonsense'))->toBe(Plan::Pro);
});
