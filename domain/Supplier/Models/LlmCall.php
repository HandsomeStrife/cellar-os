<?php

declare(strict_types=1);

namespace Domain\Supplier\Models;

use Database\Factories\LlmCallFactory;
use Domain\Shared\Traits\HasUuid;
use Domain\Supplier\Data\LlmCallData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LlmCall extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'purpose',
        'model',
        'input_tokens',
        'output_tokens',
        'cost_usd',
        'supplier_id',
        'supplier_document_id',
    ];

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cost_usd' => 'decimal:6',
        ];
    }

    public function getData(): LlmCallData
    {
        return LlmCallData::fromModel($this);
    }

    protected static function newFactory(): LlmCallFactory
    {
        return LlmCallFactory::new();
    }
}
