<?php

declare(strict_types=1);

namespace Domain\Company\Models;

use Database\Factories\CompanyFactory;
use Domain\Billing\Casts\BillingArrangementCast;
use Domain\Billing\Casts\BillingIntervalCast;
use Domain\Billing\Casts\PlanCast;
use Domain\Billing\Enums\Plan;
use Domain\Company\Data\CompanyData;
use Domain\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Billable;
use Laravel\Cashier\Subscription;

/**
 * The Company is the tenant/account. It owns users (seats), venues and supplier
 * relationships, and carries the subscription plan + Laravel Cashier billing.
 */
class Company extends Model
{
    use Billable;
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'name',
        'base_currency',
        'plan',
        'billing_arrangement',
        'custom_price_amount',
        'custom_price_currency',
        'custom_price_interval',
        'billing_notes',
        'trial_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'plan' => PlanCast::class,
            // Forgiving casts, like `plan`: an unreadable value must never
            // 500 the page you'd go to in order to fix it.
            'billing_arrangement' => BillingArrangementCast::class,
            'custom_price_interval' => BillingIntervalCast::class,
            'custom_price_amount' => 'integer',
            'trial_ends_at' => 'datetime',
        ];
    }

    /**
     * The plan their live subscription actually pays for, as opposed to the
     * tier written on the record.
     *
     * Those differ whenever an admin puts an existing subscriber on a trial of
     * a higher tier: the record says Group, Stripe is billing Pro. When the
     * trial ends, this is what they fall back to — otherwise nothing ever
     * corrects it and they keep the upgrade for free until Stripe happens to
     * send another webhook.
     */
    public function subscribedPlan(): ?Plan
    {
        $price = $this->subscriptions
            ->first(fn (Subscription $subscription) => $subscription->valid())
            ?->stripe_price;

        return is_string($price) ? Plan::forStripePrice($price) : null;
    }

    public function getData(): CompanyData
    {
        return CompanyData::fromModel($this);
    }

    protected static function newFactory(): CompanyFactory
    {
        return CompanyFactory::new();
    }
}
