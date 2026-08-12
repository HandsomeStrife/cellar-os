<?php

declare(strict_types=1);

namespace Domain\Company\Data;

use Carbon\CarbonImmutable;
use Domain\Billing\Enums\BillingArrangement;
use Domain\Billing\Enums\BillingInterval;
use Domain\Billing\Enums\Plan;
use Domain\Billing\Support\PlanEntitlement;
use Domain\Company\Models\Company;
use Domain\Shared\Data\AbstractData;
use Domain\Shared\Support\Currency;

class CompanyData extends AbstractData
{
    /**
     * NOTE for gating: `$plan` is the tier on the record, not necessarily the
     * one in force. Anything deciding what a company may DO must go through
     * {@see self::effectivePlan()}, which accounts for a trial having run out.
     */
    public function __construct(
        public ?int $id,
        public ?string $uuid,
        public string $name,
        public string $base_currency,
        public Plan $plan,
        public ?CarbonImmutable $created_at = null,
        public BillingArrangement $billing_arrangement = BillingArrangement::Standard,
        public ?int $custom_price_amount = null,
        public ?string $custom_price_currency = null,
        public ?BillingInterval $custom_price_interval = null,
        public ?string $billing_notes = null,
        public ?CarbonImmutable $trial_ends_at = null,
        /**
         * Null means "not established" — NOT "no subscription". Only
         * {@see self::fromModel()} can answer this, and a false here is a
         * downgrade switch, so anything built by hand must leave it unknown
         * rather than assert a customer isn't paying.
         */
        public ?bool $has_active_subscription = null,
    ) {}

    public static function fromModel(Company $model): self
    {
        return new self(
            id: $model->id,
            uuid: $model->uuid,
            name: $model->name,
            base_currency: $model->base_currency,
            plan: $model->plan,
            created_at: $model->created_at?->toImmutable(),
            billing_arrangement: $model->billing_arrangement ?? BillingArrangement::Standard,
            custom_price_amount: $model->custom_price_amount,
            custom_price_currency: $model->custom_price_currency,
            custom_price_interval: $model->custom_price_interval,
            billing_notes: $model->billing_notes,
            trial_ends_at: $model->trial_ends_at?->toImmutable(),
            // Cashier's own check. This is the ONLY place that can establish
            // it, which is why the property is null-by-default everywhere else.
            has_active_subscription: $model->subscribed(),
        );
    }

    /**
     * What this company may actually do right now, which is not always the
     * tier on the record — see {@see PlanEntitlement}.
     */
    public function effectivePlan(): Plan
    {
        return PlanEntitlement::resolve(
            plan: $this->plan,
            arrangement: $this->billing_arrangement,
            trialEndsAt: $this->trial_ends_at,
            hasActiveSubscription: $this->has_active_subscription,
        );
    }

    /**
     * They had a trial, it ran out, and they never started paying. True even
     * when the lapse cost them nothing — this is the commercial signal, and
     * the back office needs it whether or not access changed.
     */
    public function trialLapsed(): bool
    {
        return PlanEntitlement::hasLapsed(
            $this->billing_arrangement,
            $this->trial_ends_at,
            $this->has_active_subscription,
        );
    }

    /**
     * Is this company currently getting less than the tier on its record?
     */
    public function entitlementReduced(): bool
    {
        return $this->effectivePlan() !== $this->plan;
    }

    /**
     * Would a lapse here actually revoke anything? A trial issued ON the entry
     * tier confers nothing to lose, so counting it down at the customer only
     * promises a cliff that never arrives.
     */
    public function trialConfersAnything(): bool
    {
        return $this->plan !== Plan::default();
    }

    public function onTrial(): bool
    {
        return PlanEntitlement::onTrial($this->trial_ends_at);
    }

    public function trialDaysRemaining(): ?int
    {
        return PlanEntitlement::trialDaysRemaining($this->trial_ends_at);
    }

    /**
     * Has a trial been given and then run out? Distinct from "never had one".
     */
    public function trialExpired(): bool
    {
        return $this->trial_ends_at !== null && ! $this->onTrial();
    }

    /**
     * The agreed price, written out — "£49.00 a month". Null unless the
     * arrangement actually carries one.
     */
    public function customPriceLabel(): ?string
    {
        if ($this->custom_price_amount === null) {
            return null;
        }

        $money = Currency::format(
            $this->custom_price_amount / 100,
            $this->custom_price_currency ?? $this->base_currency,
        );

        return $this->custom_price_interval === null
            ? $money
            : $money.' '.$this->custom_price_interval->getLabel();
    }

    /**
     * What we charge, in one line, whatever the arrangement.
     */
    public function billingLabel(): string
    {
        return match ($this->billing_arrangement) {
            BillingArrangement::Comped => 'No charge',
            BillingArrangement::Custom => $this->customPriceLabel() ?? 'Custom price (not set)',
            // The list price is quoted in sterling. Repeating it at a company
            // that trades in euros would state a figure we don't charge.
            BillingArrangement::Standard => $this->base_currency === 'GBP'
                ? $this->plan->monthlyPrice().' a month'
                : 'List price',
        };
    }

    public function toModel(): Company
    {
        return Company::findOrFail($this->id);
    }
}
