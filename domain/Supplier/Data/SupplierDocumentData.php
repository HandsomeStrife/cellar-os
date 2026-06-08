<?php

declare(strict_types=1);

namespace Domain\Supplier\Data;

use Carbon\CarbonImmutable;
use Domain\Shared\Data\AbstractData;
use Domain\Supplier\Enums\SupplierDocumentStatus;
use Domain\Supplier\Models\SupplierDocument;

class SupplierDocumentData extends AbstractData
{
    public function __construct(
        public ?int $id,
        public ?string $uuid,
        public int $supplier_id,
        public ?int $uploaded_by_supplier_user_id,
        public ?int $uploaded_by_company_id,
        public ?int $uploaded_by_user_id,
        public ?string $title,
        public string $file_name,
        public ?string $file_type,
        public int $file_size,
        public string $storage_path,
        public SupplierDocumentStatus $status,
        public ?string $analysis_notes = null,
        public ?CarbonImmutable $analysed_at = null,
        public ?CarbonImmutable $created_at = null,
    ) {}

    public static function fromModel(SupplierDocument $model): self
    {
        return new self(
            id: $model->id,
            uuid: $model->uuid,
            supplier_id: $model->supplier_id,
            uploaded_by_supplier_user_id: $model->uploaded_by_supplier_user_id,
            uploaded_by_company_id: $model->uploaded_by_company_id,
            uploaded_by_user_id: $model->uploaded_by_user_id,
            title: $model->title,
            file_name: $model->file_name,
            file_type: $model->file_type,
            file_size: $model->file_size,
            storage_path: $model->storage_path,
            status: $model->status,
            analysis_notes: $model->analysis_notes,
            analysed_at: $model->analysed_at?->toImmutable(),
            created_at: $model->created_at?->toImmutable(),
        );
    }

    public function toModel(): SupplierDocument
    {
        return SupplierDocument::findOrFail($this->id);
    }
}
