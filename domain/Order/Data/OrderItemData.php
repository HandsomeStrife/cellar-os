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
        // How the wine was sold at order time. `quantity_units` is always the
        // bottle count (so receive → inventory is unit-based); these snapshot
        // the case framing for display/PDF.
        public string $sold_by_at_order = 'bottle',
        public ?int $pack_size_at_order = null,
        public ?string $pack_price_at_order = null,
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
            sold_by_at_order: $model->sold_by_at_order ?? 'bottle',
            pack_size_at_order: $model->pack_size_at_order,
            pack_price_at_order: $model->pack_price_at_order,
        );
    }

    public function toModel(): OrderItem
    {
        return OrderItem::findOrFail($this->id);
    }

    /**
     * Whether this line was ordered by the case (with a known case size).
     */
    public function soldByCaseAtOrder(): bool
    {
        return $this->sold_by_at_order === 'case' && (int) $this->pack_size_at_order > 0;
    }

    /**
     * Whole cases in this line (bottles ÷ case size), or null when sold by the
     * bottle.
     */
    public function casesAtOrder(): ?int
    {
        return $this->soldByCaseAtOrder() ? intdiv($this->quantity_units, (int) $this->pack_size_at_order) : null;
    }

    /**
     * Any leftover bottles beyond whole cases (normally 0 for case orders).
     */
    public function looseBottlesAtOrder(): int
    {
        return $this->soldByCaseAtOrder() ? $this->quantity_units % (int) $this->pack_size_at_order : 0;
    }

    /**
     * The case price at order time (the snapshot, else derived from the
     * per-bottle price × case size).
     */
    public function casePriceAtOrder(): ?string
    {
        if (! $this->soldByCaseAtOrder()) {
            return null;
        }

        if ($this->pack_price_at_order !== null) {
            return $this->pack_price_at_order;
        }

        return number_format((float) $this->unit_price_at_order * (int) $this->pack_size_at_order, 2, '.', '');
    }

    /**
     * Line total (always bottles × per-bottle price — the canonical maths).
     */
    public function lineTotal(): float
    {
        return $this->quantity_units * (float) $this->unit_price_at_order;
    }
}
