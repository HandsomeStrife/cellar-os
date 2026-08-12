<?php

declare(strict_types=1);

use App\Livewire\Admin\CompanyShow;
use Carbon\CarbonImmutable;
use Domain\Admin\Models\Admin;
use Domain\Billing\Enums\BillingArrangement;
use Domain\Billing\Enums\BillingInterval;
use Domain\Billing\Enums\Feature;
use Domain\Billing\Enums\Plan;
use Domain\Company\Models\Company;
use Domain\Company\Repositories\CompanyRepository;
use Domain\User\Models\User;
use Livewire\Livewire;

/**
 * Sign an admin in and hand back a company to work on. Done per test rather
 * than in a beforeEach so the guard test at the bottom runs genuinely signed
 * out — actingAs logs in through the SESSION, which a later request inherits.
 */
function billingFixture(): Company
{
    test()->actingAs(Admin::factory()->create(), 'admin');

    return Company::factory()->create(['name' => 'Acme Wines', 'plan' => Plan::Pro->value]);
}

it('puts a company on a custom price', function () {
    $company = billingFixture();

    Livewire::test(CompanyShow::class, ['uuid' => $company->uuid])
        ->set('plan', Plan::Group->value)
        ->set('arrangement', BillingArrangement::Custom->value)
        ->set('customPrice', '149.50')
        ->set('customCurrency', 'GBP')
        ->set('customInterval', BillingInterval::Year->value)
        ->set('billingNotes', 'Agreed with Tom, first year only.')
        ->call('saveBilling')
        ->assertHasNoErrors();

    $data = (new CompanyRepository)->find($company->id);

    expect($data->plan)->toBe(Plan::Group)
        ->and($data->billing_arrangement)->toBe(BillingArrangement::Custom)
        ->and($data->custom_price_amount)->toBe(14950)
        ->and($data->custom_price_interval)->toBe(BillingInterval::Year)
        ->and($data->billing_notes)->toBe('Agreed with Tom, first year only.')
        ->and($data->customPriceLabel())->toBe('£149.50 a year');
});

it('refuses a custom arrangement with no amount', function () {
    $company = billingFixture();

    Livewire::test(CompanyShow::class, ['uuid' => $company->uuid])
        ->set('arrangement', BillingArrangement::Custom->value)
        ->set('customPrice', '')
        ->call('saveBilling')
        ->assertHasErrors('customPrice');

    expect($company->fresh()->billing_arrangement)->toBe(BillingArrangement::Standard);
});

it('clears a stale custom price when the company goes back to list price', function () {
    // Otherwise a company reads as "Standard" while still carrying last
    // quarter's discount, and nobody notices until the invoice.
    $company = billingFixture();
    $company->update([
        'billing_arrangement' => BillingArrangement::Custom->value,
        'custom_price_amount' => 4900,
        'custom_price_currency' => 'GBP',
        'custom_price_interval' => BillingInterval::Month->value,
    ]);

    Livewire::test(CompanyShow::class, ['uuid' => $company->uuid])
        ->set('arrangement', BillingArrangement::Standard->value)
        ->call('saveBilling')
        ->assertHasNoErrors();

    $fresh = $company->fresh();

    expect($fresh->billing_arrangement)->toBe(BillingArrangement::Standard)
        ->and($fresh->custom_price_amount)->toBeNull()
        ->and($fresh->custom_price_currency)->toBeNull()
        ->and($fresh->custom_price_interval)->toBeNull();
});

it('comps a company so it pays nothing', function () {
    $company = billingFixture();

    Livewire::test(CompanyShow::class, ['uuid' => $company->uuid])
        ->set('plan', Plan::Group->value)
        ->set('arrangement', BillingArrangement::Comped->value)
        ->call('saveBilling')
        ->assertHasNoErrors();

    $data = (new CompanyRepository)->find($company->id);

    expect($data->billing_arrangement->isFree())->toBeTrue()
        ->and($data->billingLabel())->toBe('Free')
        ->and($data->effectivePlan())->toBe(Plan::Group);
});

it('grants a trial of a given length from today', function () {
    $company = billingFixture();

    Livewire::test(CompanyShow::class, ['uuid' => $company->uuid])
        ->set('plan', Plan::Group->value)
        ->call('grantTrial', 30)
        ->assertHasNoErrors();

    $data = (new CompanyRepository)->find($company->id);

    expect($data->onTrial())->toBeTrue()
        ->and($data->trialDaysRemaining())->toBe(30)
        ->and($data->effectivePlan())->toBe(Plan::Group);
});

it('ends a trial on demand without taking the plan away', function () {
    $company = billingFixture();

    Livewire::test(CompanyShow::class, ['uuid' => $company->uuid])
        ->set('plan', Plan::Group->value)
        ->call('grantTrial', 30)
        ->call('endTrial')
        ->assertHasNoErrors();

    $data = (new CompanyRepository)->find($company->id);

    // Clearing the date removes the trial rather than expiring it, so the
    // company keeps the tier it was put on. Expiring is what the clock does.
    expect($data->trial_ends_at)->toBeNull()
        ->and($data->onTrial())->toBeFalse()
        ->and($data->effectivePlan())->toBe(Plan::Group);
});

it('refuses a trial length outside anything sensible', function () {
    $company = billingFixture();

    Livewire::test(CompanyShow::class, ['uuid' => $company->uuid])
        ->call('grantTrial', 0)
        ->assertStatus(422);

    Livewire::test(CompanyShow::class, ['uuid' => $company->uuid])
        ->call('grantTrial', 5000)
        ->assertStatus(422);

    expect($company->fresh()->trial_ends_at)->toBeNull();
});

it('takes the feature away when the trial runs out', function () {
    // The whole point of a trial: a Group trial with no subscription behind
    // it must stop granting multiple venues once it lapses.
    $company = billingFixture();
    $company->update([
        'plan' => Plan::Group->value,
        'trial_ends_at' => CarbonImmutable::now()->addDay(),
    ]);

    expect((new CompanyRepository)->find($company->id)->effectivePlan()->can(Feature::MultiVenue))
        ->toBeTrue();

    $company->update(['trial_ends_at' => CarbonImmutable::now()->subDay()]);

    $lapsed = (new CompanyRepository)->find($company->id);

    expect($lapsed->effectivePlan()->can(Feature::MultiVenue))->toBeFalse()
        // …but the tier they were PUT on is remembered, so putting them back
        // is one click rather than a guess.
        ->and($lapsed->plan)->toBe(Plan::Group);
});

it('leaves a company that never had a trial exactly as it was', function () {
    $company = billingFixture();
    $company->update(['plan' => Plan::Group->value]);

    $data = (new CompanyRepository)->find($company->id);

    expect($data->trial_ends_at)->toBeNull()
        ->and($data->effectivePlan())->toBe(Plan::Group);
});

it('lets nobody but an admin change the terms', function () {
    $company = Company::factory()->create();

    $this->actingAs(User::factory()->create(['company_id' => $company->id]))
        ->get(route('admin.companies.show', $company->uuid))
        ->assertRedirect(route('admin.login'));

    expect($company->fresh()->billing_arrangement)->toBe(BillingArrangement::Standard);
});
