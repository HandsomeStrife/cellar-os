<?php

declare(strict_types=1);

use Domain\Billing\Enums\Plan;
use Domain\Company\Models\Company;
use Domain\User\Models\User;
use Domain\Venue\Models\Venue;
use Laravel\Cashier\Subscription;

function signInTo(Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $venue = Venue::factory()->create(['company_id' => $company->id]);
    DB::table('user_venue')->insert(['user_id' => $user->id, 'venue_id' => $venue->id]);

    test()->actingAs($user);

    return $user;
}

it('says nothing to a company that has never had a trial', function () {
    signInTo(Company::factory()->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('trial', false);
});

it('tells a company on a trial how long is left', function () {
    signInTo(Company::factory()->onPlan(Plan::Group)->onTrial(14)->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('14 days left')
        ->assertSee('Group trial');
});

it('explains why the app got smaller when a trial lapses', function () {
    // Silence here is how you lose a customer who thinks something broke.
    signInTo(Company::factory()->onPlan(Plan::Group)->trialExpired()->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Group trial has ended')
        // Literal text in the template, so no entity-escaping to allow for.
        ->assertSee('on Pro now');
});

it('stays quiet when a lapsed trial cost them nothing', function () {
    // A trial ON the entry tier takes nothing away, so there is nothing to
    // apologise for.
    signInTo(Company::factory()->onPlan(Plan::Pro)->trialExpired()->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('has ended');
});

it('says nothing to a company that has already subscribed', function () {
    // A leftover trial date must not have a paying customer counting down to
    // an expiry that will never come.
    $company = Company::factory()->onPlan(Plan::Group)->onTrial(10)->create();

    Subscription::create([
        'company_id' => $company->id,
        'type' => 'default',
        'stripe_id' => 'sub_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_group',
        'quantity' => 1,
    ]);

    signInTo($company);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('days left');
});

it('does not count down a trial that confers nothing', function () {
    // A trial ON the entry tier grants nothing to lose, so a countdown would
    // promise a cliff that never arrives and then vanish unexplained on the
    // last day. The expired half of this pair is covered below.
    signInTo(Company::factory()->onPlan(Plan::default())->onTrial(14)->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('days left');
});

it('never nags a comped company about its trial dates', function () {
    signInTo(Company::factory()->onPlan(Plan::Group)->comped()->trialExpired()->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('has ended')
        ->assertDontSee('days left');
});
