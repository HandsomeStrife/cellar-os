<?php

declare(strict_types=1);

namespace Domain\Catalogue\Data;

use Domain\Catalogue\Enums\WineType;
use Domain\Catalogue\Models\Lwin;
use Domain\Shared\Data\AbstractData;

class LwinData extends AbstractData
{
    public function __construct(
        public string $lwin,
        public ?string $display_name,
        public ?string $country,
        public ?string $region,
        public ?string $sub_region,
        public ?WineType $colour,
        public ?string $classification,
    ) {}

    public static function fromModel(Lwin $model): self
    {
        return new self(
            lwin: $model->lwin,
            display_name: $model->display_name,
            country: $model->country,
            region: $model->region,
            sub_region: $model->sub_region,
            colour: self::mapColour($model->colour),
            classification: $model->classification,
        );
    }

    /** LWIN colour vocabulary → ours ("Mixed" and non-wine values drop to null). */
    private static function mapColour(?string $colour): ?WineType
    {
        return match ($colour) {
            'Red' => WineType::Red,
            'White' => WineType::White,
            'Rose' => WineType::Rose,
            default => null,
        };
    }
}
