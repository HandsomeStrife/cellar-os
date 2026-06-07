<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Order\Enums\OrderStatus;
use Domain\Order\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'status' => OrderStatus::Draft->value,
            'total' => '0.00',
            'notes' => null,
        ];
    }
}
