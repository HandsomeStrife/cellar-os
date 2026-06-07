<?php

declare(strict_types=1);

namespace Domain\Catalogue\Data;

use Carbon\CarbonImmutable;
use Domain\Catalogue\Enums\WineColour;
use Domain\Catalogue\Models\Product;
use Domain\Shared\Data\AbstractData;

class ProductData extends AbstractData
{
    public function __construct(
        public ?int $id,
        public ?string $uuid,
        public ?int $supplier_id,
        public ?int $raw_upload_id,
        public string $wine_name,
        public ?string $producer,
        public ?string $country,
        public ?string $region,
        public ?string $sub_region,
        public ?array $grape,
        public ?WineColour $colour,
        public ?int $vintage,
        public int $format_ml,
        public int $case_size,
        public ?string $unit_price,
        public ?string $price_per_litre,
        public int $stock,
        public ?string $latitude,
        public ?string $longitude,
        public ?CarbonImmutable $created_at = null,
    ) {}

    public static function fromModel(Product $model): self
    {
        return new self(
            id: $model->id,
            uuid: $model->uuid,
            supplier_id: $model->supplier_id,
            raw_upload_id: $model->raw_upload_id,
            wine_name: $model->wine_name,
            producer: $model->producer,
            country: $model->country,
            region: $model->region,
            sub_region: $model->sub_region,
            grape: $model->grape,
            colour: $model->colour,
            vintage: $model->vintage,
            format_ml: $model->format_ml,
            case_size: $model->case_size,
            unit_price: $model->unit_price,
            price_per_litre: $model->price_per_litre,
            stock: $model->stock,
            latitude: $model->latitude,
            longitude: $model->longitude,
            created_at: $model->created_at?->toImmutable(),
        );
    }

    public function toModel(): Product
    {
        return Product::findOrFail($this->id);
    }
}
