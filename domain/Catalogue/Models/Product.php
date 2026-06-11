<?php

declare(strict_types=1);

namespace Domain\Catalogue\Models;

use Database\Factories\ProductFactory;
use Domain\Catalogue\Data\ProductData;
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
        'unit_price',
        'price_per_litre',
        'stock',
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
            'unit_price' => 'decimal:2',
            'price_per_litre' => 'decimal:2',
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
            'vintage' => 'integer',
            'format_ml' => 'integer',
            'case_size' => 'integer',
            'stock' => 'integer',
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
