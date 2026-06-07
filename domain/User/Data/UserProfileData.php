<?php

declare(strict_types=1);

namespace Domain\User\Data;

use Domain\Shared\Data\AbstractData;
use Domain\User\Models\UserProfile;

class UserProfileData extends AbstractData
{
    public function __construct(
        public ?int $id,
        public int $user_id,
        public ?string $profession,
        public ?string $company_name,
    ) {}

    public static function fromModel(UserProfile $model): self
    {
        return new self(
            id: $model->id,
            user_id: $model->user_id,
            profession: $model->profession,
            company_name: $model->company_name,
        );
    }

    public function toModel(): UserProfile
    {
        return UserProfile::findOrFail($this->id);
    }
}
