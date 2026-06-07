<?php

declare(strict_types=1);

namespace Domain\Order\Data;

use Domain\Order\Models\OrderItem;
use Domain\Shared\Data\AbstractData;

class OrderItemData extends AbstractData
{
    public function __construct(
        public ?int $id,
        public ?int $order_id,
        public ?int $product_id,
        public string $wine_name,
        public int $quantity_units,
        public ?string $unit_price_at_order,
        public string $currency_at_order,
    ) {}

    public static function fromModel(OrderItem $model): self
    {
        return new self(
            id: $model->id,
            order_id: $model->order_id,
            product_id: $model->product_id,
            wine_name: $model->wine_name,
            quantity_units: $model->quantity_units,
            unit_price_at_order: $model->unit_price_at_order,
            currency_at_order: $model->currency_at_order,
        );
    }

    public function toModel(): OrderItem
    {
        return OrderItem::findOrFail($this->id);
    }
}
