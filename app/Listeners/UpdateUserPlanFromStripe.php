<?php

declare(strict_types=1);

namespace App\Listeners;

use Domain\Billing\Enums\Plan;
use Domain\User\Actions\SetUserPlanAction;
use Domain\User\Repositories\UserRepository;
use Laravel\Cashier\Events\WebhookReceived;

/**
 * Keeps users.plan in step with their Stripe subscription. Cashier already
 * maintains the subscriptions table; this maps the active price back to our
 * Plan enum (and resets to Free on cancellation).
 */
class UpdateUserPlanFromStripe
{
    public function handle(WebhookReceived $event): void
    {
        // Fail closed: without a webhook signing secret Cashier cannot verify
        // the payload's authenticity, so we refuse to mutate plans from it.
        if (blank(config('cashier.webhook.secret'))) {
            return;
        }

        $payload = $event->payload;
        $type = $payload['type'] ?? '';

        if (! str_starts_with($type, 'customer.subscription.')) {
            return;
        }

        $object = $payload['data']['object'] ?? [];
        $customerId = $object['customer'] ?? null;

        if (! is_string($customerId) || $customerId === '') {
            return;
        }

        $user = (new UserRepository)->findByStripeId($customerId);

        if ($user === null) {
            return;
        }

        if ($type === 'customer.subscription.deleted') {
            (new SetUserPlanAction)->execute($user->id, Plan::Free);

            return;
        }

        // Only grant a paid plan once the subscription is genuinely active —
        // never on `incomplete` (pre-payment) or `past_due`/`unpaid`.
        $status = $object['status'] ?? null;

        if (! in_array($status, ['active', 'trialing'], true)) {
            return;
        }

        $priceId = $object['items']['data'][0]['price']['id'] ?? null;
        $plan = is_string($priceId) ? Plan::forStripePrice($priceId) : null;

        if ($plan !== null) {
            (new SetUserPlanAction)->execute($user->id, $plan);
        }
    }
}
