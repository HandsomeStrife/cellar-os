<?php

declare(strict_types=1);

namespace Domain\Catalogue\Data;

use Carbon\CarbonImmutable;
use Domain\Catalogue\Enums\WineColour;
use Domain\Catalogue\Models\WineFact;
use Domain\Shared\Data\AbstractData;

class WineFactData extends AbstractData
{
    /**
     * @param  array<int, string>|null  $grape
     * @param  array<int, string>  $conflicted_fields  field NAMES with disagreeing observations (never displayed); sources stay internal
     */
    public function __construct(
        public ?int $id,
        public ?string $uuid,
        public string $identity_key,
        public string $wine_name,
        public ?string $producer,
        public ?string $country,
        public ?string $region,
        public ?string $sub_region,
        public ?array $grape,
        public ?WineColour $colour,
        public array $conflicted_fields = [],
        public int $observations = 1,
        public ?CarbonImmutable $created_at = null,
    ) {}

    public static function fromModel(WineFact $model): self
    {
        return new self(
            id: $model->id,
            uuid: $model->uuid,
            identity_key: $model->identity_key,
            wine_name: $model->wine_name,
            producer: $model->producer,
            country: $model->country,
            region: $model->region,
            sub_region: $model->sub_region,
            grape: $model->grape,
            colour: $model->colour,
            conflicted_fields: array_keys($model->field_conflicts ?? []),
            observations: $model->observations,
            created_at: $model->created_at?->toImmutable(),
        );
    }

    public function toModel(): WineFact
    {
        return WineFact::findOrFail($this->id);
    }
}
