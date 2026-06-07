<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Order\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wine_name' => fake()->words(3, true),
            'quantity_units' => 6,
            'unit_price_at_order' => '25.00',
            'currency_at_order' => 'GBP',
        ];
    }
}
