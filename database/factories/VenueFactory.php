<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Venue\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Venue>
 */
class VenueFactory extends Factory
{
    protected $model = Venue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => fake()->company(),
            'city' => fake()->city(),
            'country' => fake()->country(),
            'base_currency' => 'GBP',
        ];
    }
}
