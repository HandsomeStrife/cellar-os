<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Supplier\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => fake()->company(),
            'contact' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'location' => fake()->city().', '.fake()->country(),
            'status' => 'Active',
            'column_mapping' => null,
        ];
    }
}
