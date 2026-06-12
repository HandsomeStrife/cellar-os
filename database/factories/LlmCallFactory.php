<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Supplier\Models\LlmCall;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LlmCallFactory extends Factory
{
    protected $model = LlmCall::class;

    public function definition(): array
    {
        $input = fake()->numberBetween(500, 50000);
        $output = fake()->numberBetween(100, 15000);

        return [
            'uuid' => (string) Str::uuid(),
            'purpose' => fake()->randomElement(['derive_mapping', 'derive_profile', 'derive_rules', 'extract_wines', 'pick_lwins']),
            'model' => 'claude-haiku-4-5-20251001',
            'input_tokens' => $input,
            'output_tokens' => $output,
            'cost_usd' => round(($input / 1_000_000) * 1.0 + ($output / 1_000_000) * 5.0, 6),
            'supplier_id' => null,
            'supplier_document_id' => null,
        ];
    }
}
