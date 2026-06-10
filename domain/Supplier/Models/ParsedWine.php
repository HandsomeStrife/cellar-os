<?php

declare(strict_types=1);

namespace Domain\Supplier\Models;

use Database\Factories\ParsedWineFactory;
use Domain\Shared\Traits\HasUuid;
use Domain\Supplier\Data\ParsedWineData;
use Domain\Supplier\Enums\ParsedWineStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParsedWine extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'supplier_document_id',
        'supplier_id',
        'payload',
        'status',
        'confidence',
        'source_ref',
        'flag',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => ParsedWineStatus::class,
            'confidence' => 'float',
        ];
    }

    public function getData(): ParsedWineData
    {
        return ParsedWineData::fromModel($this);
    }

    protected static function newFactory(): ParsedWineFactory
    {
        return ParsedWineFactory::new();
    }
}
