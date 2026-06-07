<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Inventory\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'quantity_units' => 12,
            'last_purchase_price' => '25.00',
            'last_purchase_currency' => 'GBP',
            'last_received_at' => now(),
            'is_archived' => false,
        ];
    }
}
