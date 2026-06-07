<?php

declare(strict_types=1);

use App\Listeners\UpdateUserPlanFromStripe;
use App\Livewire\Billing\Pricing;
use Domain\Billing\Enums\Plan;
use Domain\User\Models\User;
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

it('renders the pricing page with all plans', function () {
    $this->actingAs(User::factory()->create(['plan' => Plan::Free->value]));

    Livewire::test(Pricing::class)
        ->assertOk()
        ->assertSee('Starter')
        ->assertSee('Pro')
        ->assertSee('Group')
        ->assertSee('Import supplier price lists');
});

it('highlights the current plan', function () {
    $this->actingAs(User::factory()->create(['plan' => Plan::Pro->value]));

    Livewire::test(Pricing::class)->assertSee('Current');
});

it('shows a notice when billing is not configured at checkout', function () {
    config(['cashier.secret' => null]);
    $this->actingAs(User::factory()->create(['plan' => Plan::Free->value]));

    Livewire::test(Pricing::class)
        ->call('checkout', 'starter')
        ->assertDispatched('toast')
        ->assertNoRedirect();
});

it('rejects checkout for a non-paid plan', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Pricing::class)->call('checkout', 'free')->assertStatus(422);
});

it('maps a Stripe price id back to a plan', function () {
    config(['billing.prices.pro' => 'price_pro_123']);

    expect(Plan::forStripePrice('price_pro_123'))->toBe(Plan::Pro)
        ->and(Plan::forStripePrice('price_unknown'))->toBeNull();
});

it('upgrades a user\'s plan from a subscription webhook', function () {
    config(['cashier.webhook.secret' => 'whsec_test', 'billing.prices.pro' => 'price_pro_123']);
    $user = User::factory()->create(['plan' => Plan::Free->value, 'stripe_id' => 'cus_abc']);

    (new UpdateUserPlanFromStripe)->handle(
        subscriptionWebhook('customer.subscription.updated', 'cus_abc', 'price_pro_123')
    );

    expect($user->fresh()->plan)->toBe(Plan::Pro);
});

it('resets a user to free when the subscription is deleted', function () {
    config(['cashier.webhook.secret' => 'whsec_test']);
    $user = User::factory()->create(['plan' => Plan::Pro->value, 'stripe_id' => 'cus_abc']);

    (new UpdateUserPlanFromStripe)->handle(
        subscriptionWebhook('customer.subscription.deleted', 'cus_abc')
    );

    expect($user->fresh()->plan)->toBe(Plan::Free);
});

it('ignores webhooks for unknown customers', function () {
    config(['cashier.webhook.secret' => 'whsec_test', 'billing.prices.pro' => 'price_pro_123']);
    $user = User::factory()->create(['plan' => Plan::Free->value, 'stripe_id' => 'cus_abc']);

    (new UpdateUserPlanFromStripe)->handle(
        subscriptionWebhook('customer.subscription.updated', 'cus_someone_else', 'price_pro_123')
    );

    expect($user->fresh()->plan)->toBe(Plan::Free);
});

it('refuses to change plans when no webhook signing secret is set (fail closed)', function () {
    config(['cashier.webhook.secret' => null, 'billing.prices.pro' => 'price_pro_123']);
    $user = User::factory()->create(['plan' => Plan::Free->value, 'stripe_id' => 'cus_abc']);

    (new UpdateUserPlanFromStripe)->handle(
        subscriptionWebhook('customer.subscription.updated', 'cus_abc', 'price_pro_123')
    );

    expect($user->fresh()->plan)->toBe(Plan::Free);
});

it('does not grant a plan for an incomplete subscription', function () {
    config(['cashier.webhook.secret' => 'whsec_test', 'billing.prices.pro' => 'price_pro_123']);
    $user = User::factory()->create(['plan' => Plan::Free->value, 'stripe_id' => 'cus_abc']);

    (new UpdateUserPlanFromStripe)->handle(
        subscriptionWebhook('customer.subscription.updated', 'cus_abc', 'price_pro_123', status: 'incomplete')
    );

    expect($user->fresh()->plan)->toBe(Plan::Free);
});

it('registers the webhook listener', function () {
    config(['cashier.webhook.secret' => 'whsec_test', 'billing.prices.starter' => 'price_starter_123']);
    $user = User::factory()->create(['plan' => Plan::Free->value, 'stripe_id' => 'cus_evt']);

    event(subscriptionWebhook('customer.subscription.created', 'cus_evt', 'price_starter_123'));

    expect($user->fresh()->plan)->toBe(Plan::Starter);
});

it('redirects an under-entitled user to pricing via the feature middleware', function () {
    Route::get('/__test/gated', fn () => 'ok')->middleware(['web', 'auth', 'feature:createPOs']);

    $this->actingAs(User::factory()->create(['plan' => Plan::Free->value]));
    $this->get('/__test/gated')->assertRedirect(route('pricing'));

    $this->actingAs(User::factory()->create(['plan' => Plan::Starter->value]));
    $this->get('/__test/gated')->assertOk();
});
