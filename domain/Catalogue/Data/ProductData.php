<?php

declare(strict_types=1);

namespace Domain\Catalogue\Data;

use Carbon\CarbonImmutable;
use Domain\Catalogue\Enums\SellingUnit;
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
        public SellingUnit $sold_by = SellingUnit::Bottle,
        public ?string $pack_price = null,
        public ?CarbonImmutable $last_seen_at = null,
        public ?CarbonImmutable $archived_at = null,
        public ?int $source_document_id = null,
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
            sold_by: $model->sold_by ?? SellingUnit::Bottle,
            pack_price: $model->pack_price,
            last_seen_at: $model->last_seen_at?->toImmutable(),
            archived_at: $model->archived_at?->toImmutable(),
            source_document_id: $model->source_document_id,
            created_at: $model->created_at?->toImmutable(),
        );
    }

    public function toModel(): Product
    {
        return Product::findOrFail($this->id);
    }

    /**
     * Whether this wine is quoted/sold by the case rather than the bottle.
     */
    public function soldByCase(): bool
    {
        return $this->sold_by === SellingUnit::Case;
    }

    /**
     * The headline price in the supplier's native selling unit: the case price
     * when sold by the case (the supplier's exact quote, else derived from the
     * per-bottle price × case size), otherwise the per-bottle price.
     */
    public function displayPrice(): ?string
    {
        if (! $this->soldByCase()) {
            return $this->unit_price;
        }

        if ($this->pack_price !== null) {
            return $this->pack_price;
        }

        if ($this->unit_price === null) {
            return null;
        }

        return number_format((float) $this->unit_price * $this->case_size, 2, '.', '');
    }

    /**
     * The per-bottle equivalent to show alongside a case price, or null when the
     * wine is already sold by the bottle (no equivalent needed).
     */
    public function perBottleEquivalent(): ?string
    {
        return $this->soldByCase() ? $this->unit_price : null;
    }
}
