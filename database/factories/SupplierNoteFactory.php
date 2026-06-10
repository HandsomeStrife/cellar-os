<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Supplier\Models\Supplier;
use Domain\Supplier\Models\SupplierNote;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SupplierNote>
 */
class SupplierNoteFactory extends Factory
{
    protected $model = SupplierNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'supplier_id' => Supplier::factory(),
            'admin_id' => null,
            'note' => fake()->sentence(8),
        ];
    }
}
