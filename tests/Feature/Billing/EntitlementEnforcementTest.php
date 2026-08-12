<?php

declare(strict_types=1);

use App\Livewire\Billing\Pricing;
use App\Livewire\Inventory\Index as InventoryIndex;
use Carbon\CarbonImmutable;
use Domain\Billing\Enums\BillingArrangement;
use Domain\Billing\Enums\Plan;
use Domain\Company\Models\Company;
use Domain\User\Enums\Role;
use Domain\User\Models\User;
use Domain\Venue\Models\Venue;
use Laravel\Cashier\Subscription;
use Livewire\Livewire;

/**
 * These tests exist because the gating call sites can be reverted to the raw
 * `plan` column with every other test still green: proving the rule holds in
 * PlanEntitlement proves nothing about whether the APP asks it.
 *
 * Multiple venues is the only Group-only feature, so creating a second venue
 * is the one place where entitlement is observable from the outside — which
 * makes it the test that guards the whole mechanism.
 */
function tenantOn(Plan $plan, array $companyAttributes = []): array
{
    $company = Company::factory()->onPlan($plan)->create($companyAttributes);
    $user = User::factory()->create(['company_id' => $company->id, 'role' => Role::Owner->value]);
    $venue = Venue::factory()->create(['company_id' => $company->id]);
    DB::table('user_venue')->insert(['user_id' => $user->id, 'venue_id' => $venue->id]);

    test()->actingAs($user);

    return [$company, $user];
}

function attemptSecondVenue(): object
{
    return Livewire::test(InventoryIndex::class)
        ->set('venueName', 'Second Room')
        ->call('createVenue');
}

it('lets a company on a live Group trial add a second venue', function () {
    tenantOn(Plan::Group, ['trial_ends_at' => CarbonImmutable::now()->addDays(10)]);

    attemptSecondVenue()->assertHasNoErrors();

    expect(Venue::count())->toBe(2);
});

it('STOPS a lapsed Group trial adding a second venue', function () {
    // The whole feature in one assertion: if any gating site reads the raw
    // plan column instead of the effective one, this passes when it must not.
    tenantOn(Plan::Group, ['trial_ends_at' => CarbonImmutable::now()->subDay()]);

    attemptSecondVenue()->assertForbidden();

    expect(Venue::count())->toBe(1);
});

it('keeps a comped company on Group however old its trial date is', function () {
    tenantOn(Plan::Group, [
        'billing_arrangement' => BillingArrangement::Comped->value,
        'trial_ends_at' => CarbonImmutable::now()->subYear(),
    ]);

    attemptSecondVenue()->assertHasNoErrors();

    expect(Venue::count())->toBe(2);
});

it('keeps a company on an agreed price on Group after its trial ends', function () {
    // They pay us by invoice, so they can never hold a Stripe subscription.
    tenantOn(Plan::Group, [
        'billing_arrangement' => BillingArrangement::Custom->value,
        'custom_price_amount' => 12000,
        'trial_ends_at' => CarbonImmutable::now()->subMonth(),
    ]);

    attemptSecondVenue()->assertHasNoErrors();

    expect(Venue::count())->toBe(2);
});

it('keeps a paying customer on Group after their trial ends', function () {
    // This is the wiring between Cashier and the downgrade: replace
    // CompanyData::fromModel()'s subscribed() call with `false` and every
    // paying customer who ever trialled is silently demoted.
    [$company] = tenantOn(Plan::Group, ['trial_ends_at' => CarbonImmutable::now()->subMonth()]);

    Subscription::create([
        'company_id' => $company->id,
        'type' => 'default',
        'stripe_id' => 'sub_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_group',
        'quantity' => 1,
    ]);

    attemptSecondVenue()->assertHasNoErrors();

    expect(Venue::count())->toBe(2);
});

it('tells a lapsed company on the pricing page what they are actually on', function () {
    // A second gating site with an OBSERVABLE difference. The others
    // (catalogue, import, PDF, middleware) all gate Pro-level features, so
    // with only one Group-only feature they can't yet be told apart from the
    // raw column — worth knowing if a third tier ever lands.
    config()->set('features.pricing', true);

    [$company] = tenantOn(Plan::Group, ['trial_ends_at' => CarbonImmutable::now()->subDay()]);

    Livewire::test(Pricing::class)
        ->assertViewHas('currentPlan', Plan::default());
});

it('does not let a company on the entry tier add a second venue, trial or not', function () {
    // The other half of the guard: Pro never gets multi-venue, so a green
    // result above can't be an accident of the gate being off entirely.
    tenantOn(Plan::Pro, ['trial_ends_at' => CarbonImmutable::now()->addDays(10)]);

    attemptSecondVenue()->assertForbidden();

    expect(Venue::count())->toBe(1);
});
