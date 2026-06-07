<?php

declare(strict_types=1);

namespace Domain\Order\Data;

use Carbon\CarbonImmutable;
use Domain\Order\Enums\OrderStatus;
use Domain\Order\Models\Order;
use Domain\Order\Models\OrderItem;
use Domain\Shared\Data\AbstractData;

class OrderData extends AbstractData
{
    /**
     * @param  OrderItemData[]  $items
     */
    public function __construct(
        public ?int $id,
        public ?string $uuid,
        public ?int $supplier_id,
        public ?int $venue_id,
        public ?int $created_by,
        public OrderStatus $status,
        public ?string $total,
        public ?string $notes,
        public array $items,
        public ?CarbonImmutable $created_at = null,
    ) {}

    public static function fromModel(Order $model): self
    {
        return new self(
            id: $model->id,
            uuid: $model->uuid,
            supplier_id: $model->supplier_id,
            venue_id: $model->venue_id,
            created_by: $model->created_by,
            status: $model->status,
            total: $model->total,
            notes: $model->notes,
            items: $model->items->map(fn (OrderItem $i) => OrderItemData::fromModel($i))->all(),
            created_at: $model->created_at?->toImmutable(),
        );
    }

    public function toModel(): Order
    {
        return Order::findOrFail($this->id);
    }
}
