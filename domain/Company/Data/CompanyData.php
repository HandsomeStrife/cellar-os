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
        public bool $has_active_subscription = false,
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
            // Cashier's own check, so a company that genuinely pays is never
            // downgraded by an expired trial flag left lying around.
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
            BillingArrangement::Comped => 'Free',
            BillingArrangement::Custom => $this->customPriceLabel() ?? 'Custom price (not set)',
            BillingArrangement::Standard => $this->plan->monthlyPrice().' a month',
        };
    }

    public function toModel(): Company
    {
        return Company::findOrFail($this->id);
    }
}
