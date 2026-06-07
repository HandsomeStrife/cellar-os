<?php

declare(strict_types=1);

namespace Domain\Venue\Data;

use Carbon\CarbonImmutable;
use Domain\Shared\Data\AbstractData;
use Domain\Venue\Models\Venue;

class VenueData extends AbstractData
{
    public function __construct(
        public ?int $id,
        public ?string $uuid,
        public ?int $user_id,
        public string $name,
        public ?string $address,
        public ?string $city,
        public ?string $country,
        public string $base_currency,
        public ?CarbonImmutable $created_at = null,
    ) {}

    public static function fromModel(Venue $model): self
    {
        return new self(
            id: $model->id,
            uuid: $model->uuid,
            user_id: $model->user_id,
            name: $model->name,
            address: $model->address,
            city: $model->city,
            country: $model->country,
            base_currency: $model->base_currency,
            created_at: $model->created_at?->toImmutable(),
        );
    }

    public function toModel(): Venue
    {
        return Venue::findOrFail($this->id);
    }
}
