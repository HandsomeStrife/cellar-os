<?php

declare(strict_types=1);

namespace Domain\Catalogue\Models;

use Database\Factories\ProductFactory;
use Domain\Catalogue\Data\ProductData;
use Domain\Catalogue\Enums\SellingUnit;
use Domain\Catalogue\Enums\WineColour;
use Domain\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'supplier_id',
        'raw_upload_id',
        'wine_name',
        'producer',
        'country',
        'region',
        'sub_region',
        'grape',
        'colour',
        'vintage',
        'format_ml',
        'case_size',
        'sold_by',
        'unit_price',
        'pack_price',
        'price_per_litre',
        'stock',
        'last_seen_at',
        'archived_at',
        'source_document_id',
        'lwin',
        'lwin_source',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'grape' => 'array',
            'colour' => WineColour::class,
            'sold_by' => SellingUnit::class,
            'unit_price' => 'decimal:2',
            'pack_price' => 'decimal:2',
            'price_per_litre' => 'decimal:2',
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
            'vintage' => 'integer',
            'format_ml' => 'integer',
            'case_size' => 'integer',
            'stock' => 'integer',
            'last_seen_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function getData(): ProductData
    {
        return ProductData::fromModel($this);
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }
}
