<?php

declare(strict_types=1);

namespace Domain\User\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\User\Data\UserData;
use Domain\User\Enums\Role;
use Domain\User\Models\User;

class UpdateCompanyUserAction extends AbstractAction
{
    public function execute(int $userId, string $name, Role $role): UserData
    {
        $user = User::findOrFail($userId);

        $user->update([
            'full_name' => $name,
            'role' => $role->value,
        ]);

        return $user->getData();
    }
}
