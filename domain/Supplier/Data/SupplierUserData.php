<?php

declare(strict_types=1);

namespace Domain\Supplier\Data;

use Carbon\CarbonImmutable;
use Domain\Shared\Data\AbstractData;
use Domain\Supplier\Models\SupplierUser;

class SupplierUserData extends AbstractData
{
    public function __construct(
        public ?int $id,
        public ?string $uuid,
        public int $supplier_id,
        public string $name,
        public string $email,
        public bool $has_password = false,
        public ?CarbonImmutable $created_at = null,
    ) {}

    public static function fromModel(SupplierUser $model): self
    {
        return new self(
            id: $model->id,
            uuid: $model->uuid,
            supplier_id: $model->supplier_id,
            name: $model->name,
            email: $model->email,
            has_password: $model->password !== null,
            created_at: $model->created_at?->toImmutable(),
        );
    }

    public function toModel(): SupplierUser
    {
        return SupplierUser::findOrFail($this->id);
    }
}
