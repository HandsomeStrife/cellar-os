<?php

declare(strict_types=1);

namespace Domain\Supplier\Data;

use Carbon\CarbonImmutable;
use Domain\Shared\Data\AbstractData;
use Domain\Supplier\Enums\SupplierStatus;
use Domain\Supplier\Enums\SupplierTier;
use Domain\Supplier\Models\Supplier;

class SupplierData extends AbstractData
{
    public function __construct(
        public ?int $id,
        public ?string $uuid,
        public string $name,
        public ?string $contact,
        public ?string $email,
        public ?string $phone,
        public ?string $location,
        public SupplierStatus $status,
        public ?string $address = null,
        public ?string $city = null,
        public ?string $postcode = null,
        public ?string $country = null,
        public ?string $website = null,
        public ?int $created_by_company_id = null,
        public ?CarbonImmutable $onboarded_at = null,
        public ?SupplierTier $tier = null,
        public ?array $column_mapping = null,
        public ?CarbonImmutable $created_at = null,
    ) {}

    public static function fromModel(Supplier $model): self
    {
        return new self(
            id: $model->id,
            uuid: $model->uuid,
            name: $model->name,
            contact: $model->contact,
            email: $model->email,
            phone: $model->phone,
            location: $model->location,
            status: $model->status,
            address: $model->address,
            city: $model->city,
            postcode: $model->postcode,
            country: $model->country,
            website: $model->website,
            created_by_company_id: $model->created_by_company_id,
            onboarded_at: $model->onboarded_at?->toImmutable(),
            tier: SupplierTier::derive($model->created_by_company_id, $model->onboarded_at),
            column_mapping: $model->column_mapping,
            created_at: $model->created_at?->toImmutable(),
        );
    }

    public function toModel(): Supplier
    {
        return Supplier::findOrFail($this->id);
    }
}
