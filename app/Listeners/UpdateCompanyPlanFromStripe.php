<?php

declare(strict_types=1);

namespace App\Listeners;

use Domain\Billing\Enums\Plan;
use Domain\Company\Actions\ClearCompanyTrialAction;
use Domain\Company\Actions\SetCompanyPlanAction;
use Domain\Company\Repositories\CompanyRepository;
use Laravel\Cashier\Events\WebhookReceived;

/**
 * Keeps companies.plan in step with their Stripe subscription. Cashier already
 * maintains the subscriptions table; this maps the active price back to our
 * Plan enum (and resets to Free on cancellation). The billable is the Company.
 */
class UpdateCompanyPlanFromStripe
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

        $company = (new CompanyRepository)->findByStripeId($customerId);

        if ($company === null) {
            return;
        }

        // A company on agreed terms doesn't take its tier from Stripe.
        //
        // Comping a paying customer MEANS cancelling their subscription, which
        // fires `subscription.deleted` — so without this, the act of comping
        // someone quietly demotes them, while the back office goes on showing
        // a cheerful "Free" badge. Same for a negotiated price, which is
        // invoiced outside the app and has no Stripe plan to read.
        if (! $company->billing_arrangement->dependsOnStripe()) {
            return;
        }

        if ($type === 'customer.subscription.deleted') {
            // A live trial outlives the subscription. The usual reason both
            // exist at once is a retention offer — they cancelled, we gave
            // them thirty days — and the cancellation Stripe reports at period
            // end must not delete the offer made after it. The trial expiring
            // is what demotes them, with no subscription left to carry them.
            if ($company->onTrial()) {
                return;
            }

            (new SetCompanyPlanAction)->execute($company->id, Plan::default());
            (new ClearCompanyTrialAction)->execute($company->id);

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

        if ($plan === null) {
            return;
        }

        // An admin may have put an existing subscriber on a trial of a HIGHER
        // tier — "try Group for a month while you pay for Pro". Any routine
        // event afterwards (a card update, a renewal) says `active` on the Pro
        // price, and would otherwise snap them back to Pro and delete the
        // trial, silently undoing a decision someone made on purpose.
        if ($company->onTrial() && $company->plan->rank() > $plan->rank()) {
            return;
        }

        (new SetCompanyPlanAction)->execute($company->id, $plan);

        // Converting ends the trial. Nothing else clears the date, and leaving
        // it behind arms a trap: months later a declined card flips Cashier's
        // `subscribed()` to false, the long-past trial date is suddenly load
        // bearing again, and a paying customer is demoted mid-dunning — the
        // very thing the status check above refuses to do.
        if ($status === 'active') {
            (new ClearCompanyTrialAction)->execute($company->id);
        }
    }
}
