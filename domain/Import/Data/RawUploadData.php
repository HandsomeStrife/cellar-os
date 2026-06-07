<?php

declare(strict_types=1);

namespace Domain\Import\Data;

use Carbon\CarbonImmutable;
use Domain\Import\Models\RawUpload;
use Domain\Shared\Data\AbstractData;

class RawUploadData extends AbstractData
{
    public function __construct(
        public ?int $id,
        public ?string $uuid,
        public ?int $supplier_id,
        public ?int $uploaded_by,
        public string $file_name,
        public ?string $file_type,
        public ?int $row_count,
        public ?array $column_mapping,
        public ?array $rows,
        public string $status,
        public ?CarbonImmutable $created_at = null,
    ) {}

    public static function fromModel(RawUpload $model): self
    {
        return new self(
            id: $model->id,
            uuid: $model->uuid,
            supplier_id: $model->supplier_id,
            uploaded_by: $model->uploaded_by,
            file_name: $model->file_name,
            file_type: $model->file_type,
            row_count: $model->row_count,
            column_mapping: $model->column_mapping,
            rows: $model->rows,
            status: $model->status,
            created_at: $model->created_at?->toImmutable(),
        );
    }

    public function toModel(): RawUpload
    {
        return RawUpload::findOrFail($this->id);
    }
}
