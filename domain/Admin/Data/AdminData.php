<?php

declare(strict_types=1);

namespace Domain\Admin\Data;

use Carbon\CarbonImmutable;
use Domain\Admin\Models\Admin;
use Domain\Shared\Data\AbstractData;

class AdminData extends AbstractData
{
    public function __construct(
        public ?int $id,
        public ?string $uuid,
        public string $name,
        public string $email,
        public ?CarbonImmutable $created_at = null,
    ) {}

    public static function fromModel(Admin $model): self
    {
        return new self(
            id: $model->id,
            uuid: $model->uuid,
            name: $model->name,
            email: $model->email,
            created_at: $model->created_at?->toImmutable(),
        );
    }

    public function toModel(): Admin
    {
        return Admin::findOrFail($this->id);
    }
}
