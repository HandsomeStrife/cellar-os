<?php

declare(strict_types=1);

namespace Domain\Supplier\Data;

use Carbon\CarbonImmutable;
use Domain\Shared\Data\AbstractData;
use Domain\Supplier\Models\SupplierNote;

class SupplierNoteData extends AbstractData
{
    public function __construct(
        public ?int $id,
        public ?string $uuid,
        public int $supplier_id,
        public ?int $admin_id,
        public string $note,
        public ?CarbonImmutable $created_at = null,
    ) {}

    public static function fromModel(SupplierNote $model): self
    {
        return new self(
            id: $model->id,
            uuid: $model->uuid,
            supplier_id: $model->supplier_id,
            admin_id: $model->admin_id,
            note: $model->note,
            created_at: $model->created_at?->toImmutable(),
        );
    }

    public function toModel(): SupplierNote
    {
        return SupplierNote::findOrFail($this->id);
    }
}
