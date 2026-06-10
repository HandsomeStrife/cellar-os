<?php

declare(strict_types=1);

namespace Domain\Supplier\Data;

use Carbon\CarbonImmutable;
use Domain\Shared\Data\AbstractData;
use Domain\Supplier\Enums\ParsedWineStatus;
use Domain\Supplier\Models\ParsedWine;

class ParsedWineData extends AbstractData
{
    /**
     * @param  array<string, mixed>  $payload  a normalised ProductData snapshot
     */
    public function __construct(
        public ?int $id,
        public ?string $uuid,
        public int $supplier_document_id,
        public int $supplier_id,
        public array $payload,
        public ParsedWineStatus $status,
        public ?float $confidence = null,
        public ?string $source_ref = null,
        public ?string $flag = null,
        public ?CarbonImmutable $created_at = null,
    ) {}

    public static function fromModel(ParsedWine $model): self
    {
        return new self(
            id: $model->id,
            uuid: $model->uuid,
            supplier_document_id: $model->supplier_document_id,
            supplier_id: $model->supplier_id,
            payload: $model->payload ?? [],
            status: $model->status,
            confidence: $model->confidence,
            source_ref: $model->source_ref,
            flag: $model->flag,
            created_at: $model->created_at?->toImmutable(),
        );
    }

    public function toModel(): ParsedWine
    {
        return ParsedWine::findOrFail($this->id);
    }
}
