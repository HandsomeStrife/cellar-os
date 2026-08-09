<?php

declare(strict_types=1);

use App\Listeners\UpdateCompanyPlanFromStripe;
use App\Livewire\Billing\Pricing;
use Domain\Billing\Enums\Plan;
use Domain\Company\Models\Company;
use Illuminate\Support\Facades\Route;
use Laravel\Cashier\Events\WebhookReceived;
use Livewire\Livewire;

function subscriptionWebhook(string $type, string $customer, ?string $priceId = null, string $status = 'active'): WebhookReceived
{
    $object = ['customer' => $customer, 'status' => $status];

    if ($priceId !== null) {
        $object['items'] = ['data' => [['price' => ['id' => $priceId]]]];
    }

    return new WebhookReceived([
        'type' => $type,
        'data' => ['object' => $object],
    ]);
}

it('renders the pricing page with both plans', function () {
    $this->actingAs(userOnPlan(Plan::Pro));

    Livewire::test(Pricing::class)
        ->assertOk()
        ->assertSee('Pro')
        ->assertSee('Group')
        ->assertSee('Import supplier price lists')
        ->assertDontSee('Starter');
});

it('highlights the current plan', function () {
    $this->actingAs(userOnPlan(Plan::Pro));

    Livewire::test(Pricing::class)->assertSee('Current');
});

it('shows a notice when billing is not configured at checkout', function () {
    config(['cashier.secret' => null]);
    $this->actingAs(userOnPlan(Plan::Pro));

    Livewire::test(Pricing::class)
        ->call('checkout', 'group')
        ->assertDispatched('toast')
        ->assertNoRedirect();
});

it('rejects checkout for a plan that no longer exists', function () {
    $this->actingAs(userOnPlan(Plan::Pro));

    Livewire::test(Pricing::class)->call('checkout', 'starter')->assertStatus(422);
});

it('404s the pricing page while the feature flag is off', function () {
    $this->actingAs(userOnPlan(Plan::Pro));

    config(['features.pricing' => false]);
    $this->get('/pricing')->assertNotFound();

    config(['features.pricing' => true]);
    $this->get('/pricing')->assertOk();
});

it('maps a Stripe price id back to a plan', function () {
    config(['billing.prices.pro' => 'price_pro_123']);

    expect(Plan::forStripePrice('price_pro_123'))->toBe(Plan::Pro)
        ->and(Plan::forStripePrice('price_unknown'))->toBeNull();
});

it('maps a retired plan\'s Stripe price to the tier that replaced it', function () {
    config(['billing.prices.starter' => 'price_starter_123']);

    expect(Plan::forStripePrice('price_starter_123'))->toBe(Plan::Pro);
});

it('upgrades a company\'s plan from a subscription webhook', function () {
    config(['cashier.webhook.secret' => 'whsec_test', 'billing.prices.group' => 'price_group_123']);
    $company = Company::factory()->onPlan(Plan::Pro)->create(['stripe_id' => 'cus_abc']);

    (new UpdateCompanyPlanFromStripe)->handle(
        subscriptionWebhook('customer.subscription.updated', 'cus_abc', 'price_group_123')
    );

    expect($company->fresh()->plan)->toBe(Plan::Group);
});

it('drops a company back to the entry plan when the subscription is deleted', function () {
    config(['cashier.webhook.secret' => 'whsec_test']);
    $company = Company::factory()->onPlan(Plan::Group)->create(['stripe_id' => 'cus_abc']);

    (new UpdateCompanyPlanFromStripe)->handle(
        subscriptionWebhook('customer.subscription.deleted', 'cus_abc')
    );

    expect($company->fresh()->plan)->toBe(Plan::Pro);
});

it('ignores webhooks for unknown customers', function () {
    config(['cashier.webhook.secret' => 'whsec_test', 'billing.prices.group' => 'price_group_123']);
    $company = Company::factory()->onPlan(Plan::Pro)->create(['stripe_id' => 'cus_abc']);

    (new UpdateCompanyPlanFromStripe)->handle(
        subscriptionWebhook('customer.subscription.updated', 'cus_someone_else', 'price_group_123')
    );

    expect($company->fresh()->plan)->toBe(Plan::Pro);
});

it('refuses to change plans when no webhook signing secret is set (fail closed)', function () {
    config(['cashier.webhook.secret' => null, 'billing.prices.group' => 'price_group_123']);
    $company = Company::factory()->onPlan(Plan::Pro)->create(['stripe_id' => 'cus_abc']);

    (new UpdateCompanyPlanFromStripe)->handle(
        subscriptionWebhook('customer.subscription.updated', 'cus_abc', 'price_group_123')
    );

    expect($company->fresh()->plan)->toBe(Plan::Pro);
});

it('does not grant a plan for an incomplete subscription', function () {
    config(['cashier.webhook.secret' => 'whsec_test', 'billing.prices.group' => 'price_group_123']);
    $company = Company::factory()->onPlan(Plan::Pro)->create(['stripe_id' => 'cus_abc']);

    (new UpdateCompanyPlanFromStripe)->handle(
        subscriptionWebhook('customer.subscription.updated', 'cus_abc', 'price_group_123', status: 'incomplete')
    );

    expect($company->fresh()->plan)->toBe(Plan::Pro);
});

it('registers the webhook listener', function () {
    config(['cashier.webhook.secret' => 'whsec_test', 'billing.prices.group' => 'price_group_123']);
    $company = Company::factory()->onPlan(Plan::Pro)->create(['stripe_id' => 'cus_evt']);

    event(subscriptionWebhook('customer.subscription.created', 'cus_evt', 'price_group_123'));

    expect($company->fresh()->plan)->toBe(Plan::Group);
});

it('reads a company still stored on a retired plan as Pro', function () {
    $company = Company::factory()->create();
    // Simulate a row the plan-collapse migration hasn't touched (restored backup).
    Company::withoutEvents(fn () => Company::where('id', $company->id)->update(['plan' => 'starter']));

    expect($company->fresh()->plan)->toBe(Plan::Pro);
});

it('sends an under-entitled user home via the feature middleware', function () {
    Route::get('/__test/gated', fn () => 'ok')->middleware(['web', 'auth', 'feature:multiVenue']);

    $this->actingAs(userOnPlan(Plan::Pro));
    $this->get('/__test/gated')->assertRedirect(route('dashboard'));

    $this->actingAs(userOnPlan(Plan::Group));
    $this->get('/__test/gated')->assertOk();
});

it('sends an under-entitled user to pricing when self-serve plans are visible', function () {
    config(['features.pricing' => true]);
    Route::get('/__test/gated2', fn () => 'ok')->middleware(['web', 'auth', 'feature:multiVenue']);

    $this->actingAs(userOnPlan(Plan::Pro));
    $this->get('/__test/gated2')->assertRedirect(route('pricing'));
});
