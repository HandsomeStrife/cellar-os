<?php

declare(strict_types=1);

namespace Domain\Inventory\Data;

use Carbon\CarbonImmutable;
use Domain\Inventory\Models\InventoryAttachment;
use Domain\Inventory\Models\InventoryItem;
use Domain\Shared\Data\AbstractData;

class InventoryItemData extends AbstractData
{
    /**
     * @param  InventoryAttachmentData[]  $attachments
     */
    public function __construct(
        public ?int $id,
        public ?string $uuid,
        public ?int $venue_id,
        public ?int $product_id,
        public int $quantity_units,
        public ?string $last_purchase_price,
        public ?string $last_purchase_currency,
        public ?CarbonImmutable $last_received_at,
        public bool $is_archived,
        public ?CarbonImmutable $archived_at = null,
        public ?CarbonImmutable $created_at = null,
        public array $attachments = [],
    ) {}

    public static function fromModel(InventoryItem $model): self
    {
        return new self(
            id: $model->id,
            uuid: $model->uuid,
            venue_id: $model->venue_id,
            product_id: $model->product_id,
            quantity_units: $model->quantity_units,
            last_purchase_price: $model->last_purchase_price,
            last_purchase_currency: $model->last_purchase_currency,
            last_received_at: $model->last_received_at?->toImmutable(),
            is_archived: $model->is_archived,
            archived_at: $model->archived_at?->toImmutable(),
            created_at: $model->created_at?->toImmutable(),
            attachments: $model->relationLoaded('attachments')
                ? $model->attachments->map(fn (InventoryAttachment $a) => InventoryAttachmentData::fromModel($a))->all()
                : [],
        );
    }

    public function toModel(): InventoryItem
    {
        return InventoryItem::findOrFail($this->id);
    }
}
