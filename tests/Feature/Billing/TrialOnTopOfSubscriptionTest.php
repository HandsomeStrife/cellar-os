<?php

declare(strict_types=1);

use App\Listeners\UpdateCompanyPlanFromStripe;
use App\Livewire\Admin\CompanyShow;
use Carbon\CarbonImmutable;
use Domain\Admin\Models\Admin;
use Domain\Billing\Enums\Plan;
use Domain\Company\Models\Company;
use Domain\Company\Repositories\CompanyRepository;
use Domain\User\Models\User;
use Domain\Venue\Models\Venue;
use Laravel\Cashier\Events\WebhookReceived;
use Laravel\Cashier\Subscription;
use Livewire\Livewire;

/**
 * "Try Group for a month while you keep paying for Pro."
 *
 * This shape of company broke three different ways across two review rounds:
 * a routine webhook snapped them back and deleted the trial; a data migration
 * cleared the date and made the upgrade permanent and free; and nothing ever
 * corrected the plan once the trial ended.
 */
function subscriberOnHigherTrial(int $trialDays = 20): Company
{
    config()->set('billing.prices.pro', 'price_pro');
    config()->set('billing.prices.group', 'price_group');

    $company = Company::factory()->onPlan(Plan::Group)->create([
        'stripe_id' => 'cus_'.uniqid(),
        'trial_ends_at' => CarbonImmutable::now()->addDays($trialDays)->endOfDay(),
    ]);

    Subscription::create([
        'company_id' => $company->id,
        'type' => 'default',
        'stripe_id' => 'sub_'.uniqid(),
        'stripe_status' => 'active',
        // They pay for Pro. The record says Group because of the trial.
        'stripe_price' => 'price_pro',
        'quantity' => 1,
    ]);

    return $company;
}

it('gives them the trialled tier while the trial runs', function () {
    $company = subscriberOnHigherTrial();

    expect((new CompanyRepository)->find($company->id)->effectivePlan())->toBe(Plan::Group);
});

it('drops them to what they actually pay for once it ends', function () {
    // Not to the entry tier — they are a paying customer — and not left on
    // the upgrade forever, which is what happened before: nothing revisited
    // the plan until Stripe next sent a webhook, up to a whole billing cycle.
    $company = subscriberOnHigherTrial();
    $company->update(['trial_ends_at' => CarbonImmutable::now()->subDay()]);

    expect((new CompanyRepository)->find($company->id)->effectivePlan())->toBe(Plan::Pro);
});

it('tells them the upgrade is running out', function () {
    $company = subscriberOnHigherTrial(10);
    $user = User::factory()->create(['company_id' => $company->id]);
    $venue = Venue::factory()->create(['company_id' => $company->id]);
    DB::table('user_venue')->insert(['user_id' => $user->id, 'venue_id' => $venue->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('10 days left');
});

it('does not clear a running trial when backfilling stale ones', function () {
    // The migration and the webhook guard disagreed about what a trial date on
    // a subscriber means, and the migration won at deploy time: it wiped the
    // date, leaving a permanent free upgrade that showed as neither a trial
    // nor a lapse.
    $running = subscriberOnHigherTrial(30);
    $stale = Company::factory()->onPlan(Plan::Group)->create([
        'stripe_id' => 'cus_stale',
        'trial_ends_at' => CarbonImmutable::now()->subMonth(),
    ]);
    Subscription::create([
        'company_id' => $stale->id,
        'type' => 'default',
        'stripe_id' => 'sub_stale',
        'stripe_status' => 'active',
        'stripe_price' => 'price_group',
        'quantity' => 1,
    ]);

    $migration = require database_path('migrations/2026_08_12_010000_clear_stale_trials_for_subscribers.php');
    $migration->up();

    expect($running->fresh()->trial_ends_at)->not->toBeNull()
        ->and($stale->fresh()->trial_ends_at)->toBeNull();
});

it('does not revive a trial an admin just ended, on the next ordinary save', function () {
    // "End it now" writes a moment ago. The form field only carries a DAY, so
    // re-deriving it rounded back up to end-of-day and brought the trial back
    // to life for the rest of today — undone by someone editing a note.
    $this->actingAs(Admin::factory()->create(), 'admin');
    $company = Company::factory()->onPlan(Plan::Group)->create();

    Livewire::test(CompanyShow::class, ['uuid' => $company->uuid])
        ->call('grantTrial', 30)
        ->call('endTrialNow')
        ->set('billingNotes', 'Cut short, they went elsewhere.')
        ->call('saveBilling')
        ->assertHasNoErrors();

    $data = (new CompanyRepository)->find($company->id);

    expect($data->onTrial())->toBeFalse()
        ->and($data->effectivePlan())->toBe(Plan::default());
});

it('ends an admin-granted trial when the subscription is cancelled', function () {
    config()->set('cashier.webhook.secret', 'whsec_test');

    $company = subscriberOnHigherTrial();

    (new UpdateCompanyPlanFromStripe)->handle(
        new WebhookReceived([
            'type' => 'customer.subscription.deleted',
            'data' => ['object' => ['customer' => $company->stripe_id]],
        ]),
    );

    $fresh = $company->fresh();

    expect($fresh->plan)->toBe(Plan::default())
        // Otherwise the screen reads "20 days left" on a tier they were just
        // demoted from.
        ->and($fresh->trial_ends_at)->toBeNull();
});
