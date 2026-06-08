<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Enquiry\Models\Enquiry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Enquiry>
 */
class EnquiryFactory extends Factory
{
    protected $model = Enquiry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'company' => fake()->company(),
            'message' => fake()->paragraph(),
            'status' => 'new',
            'handled_at' => null,
        ];
    }
}
