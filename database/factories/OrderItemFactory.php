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
            'sold_by_at_order' => 'bottle',
            'unit_price_at_order' => '25.00',
            'currency_at_order' => 'GBP',
        ];
    }

    /**
     * A line ordered by the case (quantity is bottles = cases × case size).
     */
    public function soldByCase(int $caseSize = 6, ?float $packPrice = null): static
    {
        return $this->state(fn (array $attributes) => [
            'sold_by_at_order' => 'case',
            'pack_size_at_order' => $caseSize,
            'pack_price_at_order' => $packPrice !== null ? number_format($packPrice, 2, '.', '') : null,
        ]);
    }
}
