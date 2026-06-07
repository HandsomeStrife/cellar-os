<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Inventory\Models\InventoryAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InventoryAttachment>
 */
class InventoryAttachmentFactory extends Factory
{
    protected $model = InventoryAttachment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'file_name' => fake()->word().'.pdf',
            'file_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(1024, 5242880),
            'storage_path' => 'inventory-attachments/'.fake()->uuid(),
        ];
    }
}
