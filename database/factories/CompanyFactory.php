<?php

declare(strict_types=1);

namespace Database\Factories;

use Carbon\CarbonImmutable;
use Domain\Billing\Enums\BillingArrangement;
use Domain\Billing\Enums\BillingInterval;
use Domain\Billing\Enums\Plan;
use Domain\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => fake()->company(),
            'base_currency' => 'GBP',
            'plan' => Plan::default()->value,
            'billing_arrangement' => BillingArrangement::Standard->value,
        ];
    }

    public function onPlan(Plan $plan): static
    {
        return $this->state(fn () => ['plan' => $plan->value]);
    }

    /** Free, indefinitely. */
    public function comped(): static
    {
        return $this->state(fn () => ['billing_arrangement' => BillingArrangement::Comped->value]);
    }

    /** A negotiated price, in minor units. */
    public function customPrice(int $amount, string $currency = 'GBP', BillingInterval $interval = BillingInterval::Month): static
    {
        return $this->state(fn () => [
            'billing_arrangement' => BillingArrangement::Custom->value,
            'custom_price_amount' => $amount,
            'custom_price_currency' => $currency,
            'custom_price_interval' => $interval->value,
        ]);
    }

    /**
     * A trial with days still to run. Ends at the END of that day, matching
     * what the admin screen sets — otherwise a "14 day" trial reads as 13,
     * being a few hours short of fourteen whole days.
     */
    public function onTrial(int $days = 30): static
    {
        return $this->state(fn () => ['trial_ends_at' => CarbonImmutable::now()->addDays($days)->endOfDay()]);
    }

    /** A trial that has already run out. */
    public function trialExpired(int $daysAgo = 1): static
    {
        return $this->state(fn () => ['trial_ends_at' => CarbonImmutable::now()->subDays($daysAgo)]);
    }
}
