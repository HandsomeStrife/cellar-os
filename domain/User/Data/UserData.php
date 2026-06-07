<?php

declare(strict_types=1);

namespace Domain\User\Data;

use Carbon\CarbonImmutable;
use Domain\Billing\Enums\Plan;
use Domain\Shared\Data\AbstractData;
use Domain\User\Models\User;

class UserData extends AbstractData
{
    public function __construct(
        public ?int $id,
        public ?string $uuid,
        public ?string $full_name,
        public string $email,
        public string $role,
        public Plan $plan,
        public ?CarbonImmutable $created_at = null,
    ) {}

    public static function fromModel(User $model): self
    {
        return new self(
            id: $model->id,
            uuid: $model->uuid,
            full_name: $model->full_name,
            email: $model->email,
            role: $model->role,
            plan: $model->plan,
            created_at: $model->created_at?->toImmutable(),
        );
    }

    public function toModel(): User
    {
        return User::findOrFail($this->id);
    }
}
