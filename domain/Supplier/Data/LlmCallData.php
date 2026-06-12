<?php

declare(strict_types=1);

namespace Domain\Supplier\Data;

use Carbon\CarbonImmutable;
use Domain\Shared\Data\AbstractData;
use Domain\Supplier\Models\LlmCall;

class LlmCallData extends AbstractData
{
    public function __construct(
        public ?int $id,
        public ?string $uuid,
        public string $purpose,
        public string $model,
        public int $input_tokens,
        public int $output_tokens,
        public string $cost_usd,
        public ?int $supplier_id = null,
        public ?int $supplier_document_id = null,
        // Display-only enrichment, filled when the repository joins the names.
        public ?string $supplier_name = null,
        public ?string $document_file = null,
        public ?CarbonImmutable $created_at = null,
    ) {}

    public static function fromModel(LlmCall $model): self
    {
        return new self(
            id: $model->id,
            uuid: $model->uuid,
            purpose: $model->purpose,
            model: $model->model,
            input_tokens: $model->input_tokens,
            output_tokens: $model->output_tokens,
            cost_usd: (string) $model->cost_usd,
            supplier_id: $model->supplier_id,
            supplier_document_id: $model->supplier_document_id,
            supplier_name: $model->supplier_name ?? null,
            document_file: $model->document_file ?? null,
            created_at: $model->created_at?->toImmutable(),
        );
    }

    public function toModel(): LlmCall
    {
        return LlmCall::findOrFail($this->id);
    }
}
