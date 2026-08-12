<?php

declare(strict_types=1);

use App\Listeners\UpdateCompanyPlanFromStripe;
use Carbon\CarbonImmutable;
use Domain\Billing\Enums\Plan;
use Domain\Company\Models\Company;
use Laravel\Cashier\Events\WebhookReceived;

beforeEach(function () {
    // The listener fails closed without a signing secret.
    config()->set('cashier.webhook.secret', 'whsec_test');
    config()->set('billing.prices.group', 'price_group');
});

function fireWebhook(string $type, Company $company, array $object = []): void
{
    (new UpdateCompanyPlanFromStripe)->handle(new WebhookReceived([
        'type' => $type,
        'data' => ['object' => array_merge(['customer' => $company->stripe_id], $object)],
    ]));
}

it('does not let a cancelled subscription undo a comp', function () {
    // Comping a paying customer MEANS cancelling their Stripe subscription,
    // which fires this very event. Without the guard, the act of comping
    // someone silently demotes them while the badge still says "Free".
    $company = Company::factory()->onPlan(Plan::Group)->comped()->create(['stripe_id' => 'cus_comped']);

    fireWebhook('customer.subscription.deleted', $company);

    expect($company->fresh()->plan)->toBe(Plan::Group);
});

it('does not let a cancelled subscription undo an agreed price', function () {
    $company = Company::factory()->onPlan(Plan::Group)
        ->customPrice(12000)
        ->create(['stripe_id' => 'cus_custom']);

    fireWebhook('customer.subscription.deleted', $company);

    expect($company->fresh()->plan)->toBe(Plan::Group);
});

it('still drops an ordinary company to the entry tier when they cancel', function () {
    $company = Company::factory()->onPlan(Plan::Group)->create(['stripe_id' => 'cus_standard']);

    fireWebhook('customer.subscription.deleted', $company);

    expect($company->fresh()->plan)->toBe(Plan::default());
});

it('clears the trial when a subscription goes active', function () {
    // Otherwise the date stays behind and becomes load-bearing again months
    // later, the first time a card is declined.
    $company = Company::factory()->onPlan(Plan::Pro)->onTrial(5)->create(['stripe_id' => 'cus_converting']);

    fireWebhook('customer.subscription.updated', $company, [
        'status' => 'active',
        'items' => ['data' => [['price' => ['id' => 'price_group']]]],
    ]);

    $fresh = $company->fresh();

    expect($fresh->plan)->toBe(Plan::Group)
        ->and($fresh->trial_ends_at)->toBeNull();
});

it('keeps a converted customer on their plan when a card is later declined', function () {
    // The regression this is really guarding: trial in March, convert, pay for
    // months, card declines in August. Cashier's subscribed() goes false, and
    // a leftover trial date would demote them mid-dunning — exactly what the
    // listener refuses to do directly.
    $company = Company::factory()->onPlan(Plan::Group)->onTrial(5)->create(['stripe_id' => 'cus_dunning']);

    fireWebhook('customer.subscription.updated', $company, [
        'status' => 'active',
        'items' => ['data' => [['price' => ['id' => 'price_group']]]],
    ]);

    // Months pass; the card fails. No subscription row exists in this test, so
    // subscribed() is false — the worst case for the company.
    $this->travelTo(CarbonImmutable::now()->addMonths(5));

    expect($company->fresh()->getData()->effectivePlan())->toBe(Plan::Group);
});

it('does not touch the trial on a trialing subscription', function () {
    $company = Company::factory()->onPlan(Plan::Pro)->onTrial(5)->create(['stripe_id' => 'cus_trialing']);

    fireWebhook('customer.subscription.updated', $company, [
        'status' => 'trialing',
        'items' => ['data' => [['price' => ['id' => 'price_group']]]],
    ]);

    expect($company->fresh()->trial_ends_at)->not->toBeNull();
});

it('does not let a routine event undo an admin-granted trial of a higher tier', function () {
    // "Try Group for a month while you keep paying for Pro." The next card
    // update or renewal says `active` on the Pro price, and would otherwise
    // snap them back to Pro and delete the trial an admin set on purpose.
    config()->set('billing.prices.pro', 'price_pro');

    $company = Company::factory()->onPlan(Plan::Group)->onTrial(20)->create(['stripe_id' => 'cus_upgrading']);

    fireWebhook('customer.subscription.updated', $company, [
        'status' => 'active',
        'items' => ['data' => [['price' => ['id' => 'price_pro']]]],
    ]);

    $fresh = $company->fresh();

    expect($fresh->plan)->toBe(Plan::Group)
        ->and($fresh->trial_ends_at)->not->toBeNull();
});

it('still records an upgrade bought through Stripe', function () {
    // The mirror image: paying for MORE than the record says must apply.
    config()->set('billing.prices.pro', 'price_pro');

    $company = Company::factory()->onPlan(Plan::Pro)->onTrial(20)->create(['stripe_id' => 'cus_upgraded']);

    fireWebhook('customer.subscription.updated', $company, [
        'status' => 'active',
        'items' => ['data' => [['price' => ['id' => 'price_group']]]],
    ]);

    expect($company->fresh()->plan)->toBe(Plan::Group);
});

it('refuses to act at all without a signing secret', function () {
    config()->set('cashier.webhook.secret', null);

    $company = Company::factory()->onPlan(Plan::Group)->create(['stripe_id' => 'cus_unsigned']);

    fireWebhook('customer.subscription.deleted', $company);

    expect($company->fresh()->plan)->toBe(Plan::Group);
});
