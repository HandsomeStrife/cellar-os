<?php

declare(strict_types=1);

use App\Livewire\Admin\Companies;
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
use Laravel\Cashier\Subscription;
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
        ->and($data->billingLabel())->toBe('No charge')
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

it('removes a trial without taking the plan away', function () {
    $company = billingFixture();

    Livewire::test(CompanyShow::class, ['uuid' => $company->uuid])
        ->set('plan', Plan::Group->value)
        ->call('grantTrial', 30)
        ->call('removeTrial')
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

it('will not silently backdate a trial into the past', function () {
    // A mistyped year would otherwise be a silent entitlement removal: save
    // "2020-01-01" and a Group company drops to Pro with no confirmation.
    // Cutting a trial short is what "End it now" is for.
    $company = billingFixture();
    $company->update(['plan' => Plan::Group->value]);

    Livewire::test(CompanyShow::class, ['uuid' => $company->uuid])
        ->set('plan', Plan::Group->value)
        ->set('trialEndsAt', '2020-01-01')
        ->call('saveBilling')
        ->assertHasErrors('trialEndsAt');

    expect($company->fresh()->trial_ends_at)->toBeNull();
});

it('ends a trial now, which is different from removing it', function () {
    $company = billingFixture();

    Livewire::test(CompanyShow::class, ['uuid' => $company->uuid])
        ->set('plan', Plan::Group->value)
        ->call('grantTrial', 30)
        ->call('endTrialNow')
        ->assertHasNoErrors();

    $data = (new CompanyRepository)->find($company->id);

    expect($data->onTrial())->toBeFalse()
        ->and($data->trialLapsed())->toBeTrue()
        ->and($data->effectivePlan())->toBe(Plan::default());
});

it('shows the arrangement, the amount and the trial state on the companies list', function () {
    test()->actingAs(Admin::factory()->create(), 'admin');

    Company::factory()->onPlan(Plan::Group)->customPrice(14950)->create(['name' => 'Agreed Terms Ltd']);
    Company::factory()->onPlan(Plan::Group)->comped()->create(['name' => 'Partner Ltd']);
    Company::factory()->onPlan(Plan::Group)->trialExpired()->create(['name' => 'Lapsed Ltd']);

    Livewire::test(Companies::class)
        ->assertSee('Custom price')
        ->assertSee('£149.50 a month')
        ->assertSee('Free')
        ->assertSee('Lapsed');
});

it('warns when agreed terms are contradicted by a live Stripe subscription', function () {
    // Marking a company Free while Stripe keeps charging it makes the badge a
    // lie. Say so on the screen rather than letting the invoice say it.
    $company = billingFixture();
    $company->update(['billing_arrangement' => BillingArrangement::Comped->value]);

    Subscription::create([
        'company_id' => $company->id,
        'type' => 'default',
        'stripe_id' => 'sub_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_group',
        'quantity' => 1,
    ]);

    Livewire::test(CompanyShow::class, ['uuid' => $company->uuid])
        ->assertSee('Stripe is still billing this company');
});

it('does not claim access was cut when a lapsed trial took nothing away', function () {
    // The revenue leak the review caught: a trial on the ENTRY tier expires
    // and revokes nothing, because there is nothing below it. Stamping a red
    // "trial ended" made three screens agree on something that never happened.
    test()->actingAs(Admin::factory()->create(), 'admin');
    $company = Company::factory()->onPlan(Plan::default())->trialExpired()->create();

    Livewire::test(CompanyShow::class, ['uuid' => $company->uuid])
        ->assertSee('they keep full access')
        ->assertDontSee('Getting Pro only');

    expect((new CompanyRepository)->find($company->id))
        ->trialLapsed()->toBeTrue()
        ->entitlementReduced()->toBeFalse();
});

it('says plainly when a lapsed trial DID take something away', function () {
    test()->actingAs(Admin::factory()->create(), 'admin');
    $company = Company::factory()->onPlan(Plan::Group)->trialExpired()->create();

    Livewire::test(CompanyShow::class, ['uuid' => $company->uuid])
        ->assertSee('Getting Pro only')
        ->assertDontSee('they keep full access');
});

it('refuses a price of nothing, which is a comp wearing a disguise', function () {
    // £0.00 stored as a "custom price" is an untracked free account: it reads
    // as a negotiated deal, and because a custom arrangement never depends on
    // Stripe it is exempt from every downgrade.
    $company = billingFixture();

    Livewire::test(CompanyShow::class, ['uuid' => $company->uuid])
        ->set('arrangement', BillingArrangement::Custom->value)
        ->set('customPrice', '0')
        ->call('saveBilling')
        ->assertHasErrors('customPrice');

    expect($company->fresh()->custom_price_amount)->toBeNull();
});

it('can still save a company whose trial already ran out', function () {
    // The trap the past-date rule set for itself: every lapsed company carries
    // a date in the past, so refusing all of them made their billing form
    // unsaveable — and the only way through, blanking the field, reads as
    // "never had a trial" and hands the plan back free.
    $company = billingFixture();
    $company->update([
        'plan' => Plan::Group->value,
        'trial_ends_at' => CarbonImmutable::now()->subMonth(),
    ]);

    Livewire::test(CompanyShow::class, ['uuid' => $company->uuid])
        ->set('billingNotes', 'Chased them on Tuesday.')
        ->call('saveBilling')
        ->assertHasNoErrors();

    $data = (new CompanyRepository)->find($company->id);

    // Saved, and still lapsed: the downgrade survives an ordinary edit.
    expect($data->billing_notes)->toBe('Chased them on Tuesday.')
        ->and($data->trialLapsed())->toBeTrue()
        ->and($data->effectivePlan())->toBe(Plan::default());
});

it('survives an unreadable arrangement rather than 500ing the page', function () {
    // The whole point of the forgiving cast: a value we can't read must not
    // take down the admin screen you would go to in order to fix it.
    $company = billingFixture();
    DB::table('companies')->where('id', $company->id)->update(['billing_arrangement' => 'gibberish']);

    Livewire::test(CompanyShow::class, ['uuid' => $company->uuid])
        ->assertOk();

    expect((new CompanyRepository)->find($company->id)->billing_arrangement)
        ->toBe(BillingArrangement::Standard);
});

it('lets nobody but an admin change the terms', function () {
    $company = Company::factory()->create();
    $outsider = User::factory()->create(['company_id' => $company->id]);

    $this->actingAs($outsider)
        ->get(route('admin.companies.show', $company->uuid))
        ->assertRedirect(route('admin.login'));

    // …and the component's own guard, not just the route's.
    Livewire::actingAs($outsider)
        ->test(CompanyShow::class, ['uuid' => $company->uuid])
        ->set('arrangement', BillingArrangement::Comped->value)
        ->call('saveBilling')
        ->assertForbidden();

    expect($company->fresh()->billing_arrangement)->toBe(BillingArrangement::Standard);
});
