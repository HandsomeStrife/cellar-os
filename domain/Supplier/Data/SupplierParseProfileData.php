<?php

declare(strict_types=1);

namespace Domain\Supplier\Data;

use Carbon\CarbonImmutable;
use Domain\Shared\Data\AbstractData;
use Domain\Supplier\Enums\ParseMode;
use Domain\Supplier\Models\SupplierParseProfile;

class SupplierParseProfileData extends AbstractData
{
    /**
     * @param  array<string, mixed>  $recipe
     */
    public function __construct(
        public ?int $id,
        public ?string $uuid,
        public int $supplier_id,
        public ?int $company_id,
        public ParseMode $mode,
        public array $recipe,
        public ?string $model,
        public ?float $confidence,
        public ?int $source_document_id,
        public bool $is_active = true,
        public ?CarbonImmutable $created_at = null,
    ) {}

    public static function fromModel(SupplierParseProfile $model): self
    {
        return new self(
            id: $model->id,
            uuid: $model->uuid,
            supplier_id: $model->supplier_id,
            company_id: $model->company_id,
            mode: $model->mode,
            recipe: $model->recipe ?? [],
            model: $model->model,
            confidence: $model->confidence,
            source_document_id: $model->source_document_id,
            is_active: $model->is_active,
            created_at: $model->created_at?->toImmutable(),
        );
    }

    public function toModel(): SupplierParseProfile
    {
        return SupplierParseProfile::findOrFail($this->id);
    }
}
