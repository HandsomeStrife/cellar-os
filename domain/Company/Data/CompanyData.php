<?php

declare(strict_types=1);

namespace Domain\Company\Data;

use Carbon\CarbonImmutable;
use Domain\Billing\Enums\Plan;
use Domain\Company\Models\Company;
use Domain\Shared\Data\AbstractData;

class CompanyData extends AbstractData
{
    public function __construct(
        public ?int $id,
        public ?string $uuid,
        public string $name,
        public string $base_currency,
        public Plan $plan,
        public ?CarbonImmutable $created_at = null,
    ) {}

    public static function fromModel(Company $model): self
    {
        return new self(
            id: $model->id,
            uuid: $model->uuid,
            name: $model->name,
            base_currency: $model->base_currency,
            plan: $model->plan,
            created_at: $model->created_at?->toImmutable(),
        );
    }

    public function toModel(): Company
    {
        return Company::findOrFail($this->id);
    }
}
