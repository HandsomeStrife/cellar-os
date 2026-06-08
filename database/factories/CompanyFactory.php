<?php

declare(strict_types=1);

namespace Database\Factories;

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
            'plan' => Plan::Free->value,
        ];
    }

    public function onPlan(Plan $plan): static
    {
        return $this->state(fn () => ['plan' => $plan->value]);
    }
}
