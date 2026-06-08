<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Supplier\Models\Supplier;
use Domain\Supplier\Models\SupplierUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<SupplierUser>
 */
class SupplierUserFactory extends Factory
{
    protected $model = SupplierUser::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'supplier_id' => Supplier::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
        ];
    }

    /**
     * An invited-but-not-yet-activated account (no password set).
     */
    public function invited(): static
    {
        return $this->state(fn () => ['password' => null]);
    }
}
