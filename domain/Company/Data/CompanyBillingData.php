<?php

declare(strict_types=1);

namespace Domain\Company\Data;

use Carbon\CarbonImmutable;
use Domain\Billing\Enums\BillingArrangement;
use Domain\Billing\Enums\BillingInterval;
use Domain\Billing\Enums\Plan;
use Domain\Shared\Data\AbstractData;

/**
 * The commercial terms for one company, as the back office sets them.
 *
 * Separate from {@see CompanyData} because this is the write side: the fields
 * an administrator is allowed to change about how a company pays, without
 * handing them the whole record.
 */
class CompanyBillingData extends AbstractData
{
    public function __construct(
        public Plan $plan,
        public BillingArrangement $billing_arrangement = BillingArrangement::Standard,
        public ?int $custom_price_amount = null,
        public ?string $custom_price_currency = null,
        public ?BillingInterval $custom_price_interval = null,
        public ?string $billing_notes = null,
        public ?CarbonImmutable $trial_ends_at = null,
    ) {}

    /**
     * Terms with the custom-price fields cleared unless the arrangement
     * actually uses them, so a stale amount can't linger behind a company
     * that has been moved back to list price.
     */
    public function normalised(): self
    {
        if ($this->billing_arrangement->needsPrice()) {
            return $this;
        }

        return new self(
            plan: $this->plan,
            billing_arrangement: $this->billing_arrangement,
            custom_price_amount: null,
            custom_price_currency: null,
            custom_price_interval: null,
            billing_notes: $this->billing_notes,
            trial_ends_at: $this->trial_ends_at,
        );
    }
}
