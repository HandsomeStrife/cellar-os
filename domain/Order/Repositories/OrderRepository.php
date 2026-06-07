<?php

declare(strict_types=1);

namespace Domain\Order\Repositories;

use Domain\Order\Data\OrderData;
use Domain\Order\Enums\OrderStatus;
use Domain\Order\Models\Order;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderRepository
{
    public function find(int $id): ?OrderData
    {
        return Order::with('items')->find($id)?->getData();
    }

    public function findByUuid(string $uuid): ?OrderData
    {
        return Order::with('items')->where('uuid', $uuid)->first()?->getData();
    }

    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return Order::with('items')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->through(fn (Order $order) => $order->getData());
    }

    public function byStatus(OrderStatus $status, int $perPage = 20): LengthAwarePaginator
    {
        return Order::with('items')
            ->where('status', $status->value)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->through(fn (Order $order) => $order->getData());
    }

    public function count(): int
    {
        return Order::count();
    }

    public function countOpen(): int
    {
        return Order::whereIn('status', [
            OrderStatus::Draft->value,
            OrderStatus::Pending->value,
            OrderStatus::Sent->value,
        ])->count();
    }
}
