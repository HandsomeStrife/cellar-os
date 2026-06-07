<?php

declare(strict_types=1);

namespace Domain\Inventory\Data;

use Carbon\CarbonImmutable;
use Domain\Inventory\Models\InventoryAttachment;
use Domain\Shared\Data\AbstractData;

class InventoryAttachmentData extends AbstractData
{
    public function __construct(
        public ?int $id,
        public ?string $uuid,
        public int $inventory_item_id,
        public ?int $uploaded_by,
        public string $file_name,
        public string $file_type,
        public int $file_size,
        public string $storage_path,
        public ?CarbonImmutable $created_at = null,
    ) {}

    public static function fromModel(InventoryAttachment $model): self
    {
        return new self(
            id: $model->id,
            uuid: $model->uuid,
            inventory_item_id: $model->inventory_item_id,
            uploaded_by: $model->uploaded_by,
            file_name: $model->file_name,
            file_type: $model->file_type,
            file_size: $model->file_size,
            storage_path: $model->storage_path,
            created_at: $model->created_at?->toImmutable(),
        );
    }

    public function toModel(): InventoryAttachment
    {
        return InventoryAttachment::findOrFail($this->id);
    }
}
