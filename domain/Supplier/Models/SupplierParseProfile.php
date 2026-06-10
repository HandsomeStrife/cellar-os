<?php

declare(strict_types=1);

namespace Domain\Supplier\Models;

use Database\Factories\SupplierParseProfileFactory;
use Domain\Shared\Traits\HasUuid;
use Domain\Supplier\Data\SupplierParseProfileData;
use Domain\Supplier\Enums\ParseMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierParseProfile extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'supplier_id',
        'company_id',
        'mode',
        'recipe',
        'model',
        'confidence',
        'source_document_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'mode' => ParseMode::class,
            'recipe' => 'array',
            'confidence' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function getData(): SupplierParseProfileData
    {
        return SupplierParseProfileData::fromModel($this);
    }

    protected static function newFactory(): SupplierParseProfileFactory
    {
        return SupplierParseProfileFactory::new();
    }
}
