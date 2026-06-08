<?php

declare(strict_types=1);

namespace Domain\Order\Repositories;

use Domain\Order\Data\OrderData;
use Domain\Order\Enums\OrderStatus;
use Domain\Order\Models\Order;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class OrderRepository
{
    /**
     * Find an order only if it belongs to the given company (tenant guard).
     * There is intentionally no unscoped find() — every lookup must be
     * tenant-scoped so an order can't leak across companies.
     */
    public function findForCompany(int $id, int $companyId): ?OrderData
    {
        return Order::with('items')->where('company_id', $companyId)->find($id)?->getData();
    }

    public function findByUuid(string $uuid): ?OrderData
    {
        return Order::with('items')->where('uuid', $uuid)->first()?->getData();
    }

    public function paginate(int $companyId, int $perPage = 20): LengthAwarePaginator
    {
        return Order::with('items')
            ->where('company_id', $companyId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->through(fn (Order $order) => $order->getData());
    }

    public function byStatus(int $companyId, OrderStatus $status, int $perPage = 20): LengthAwarePaginator
    {
        return Order::with('items')
            ->where('company_id', $companyId)
            ->where('status', $status->value)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->through(fn (Order $order) => $order->getData());
    }

    public function count(int $companyId): int
    {
        return Order::where('company_id', $companyId)->count();
    }

    /**
     * Platform-wide order count — for the admin overview only (not tenant-scoped).
     */
    public function countAll(): int
    {
        return Order::count();
    }

    public function countOpen(int $companyId): int
    {
        return Order::where('company_id', $companyId)->whereIn('status', [
            OrderStatus::Draft->value,
            OrderStatus::Pending->value,
            OrderStatus::Sent->value,
        ])->count();
    }

    public function countByStatus(int $companyId, OrderStatus $status): int
    {
        return Order::where('company_id', $companyId)->where('status', $status->value)->count();
    }

    public function totalValue(int $companyId): float
    {
        return (float) Order::where('company_id', $companyId)->sum('total');
    }

    /**
     * @return Collection<int, OrderData>
     */
    public function recent(int $companyId, int $limit = 5): Collection
    {
        return Order::with('items')
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Order $order) => $order->getData());
    }
}
