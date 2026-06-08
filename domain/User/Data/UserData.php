<?php

declare(strict_types=1);

namespace Domain\User\Data;

use Carbon\CarbonImmutable;
use Domain\Shared\Data\AbstractData;
use Domain\User\Enums\Role;
use Domain\User\Models\User;

class UserData extends AbstractData
{
    public function __construct(
        public ?int $id,
        public ?string $uuid,
        public ?int $company_id,
        public ?string $full_name,
        public string $email,
        public Role $role,
        public bool $has_password = false,
        public ?CarbonImmutable $created_at = null,
    ) {}

    public static function fromModel(User $model): self
    {
        return new self(
            id: $model->id,
            uuid: $model->uuid,
            company_id: $model->company_id,
            full_name: $model->full_name,
            email: $model->email,
            role: Role::from($model->role),
            has_password: $model->password !== null,
            created_at: $model->created_at?->toImmutable(),
        );
    }

    public function toModel(): User
    {
        return User::findOrFail($this->id);
    }
}
