<?php

declare(strict_types=1);

namespace Domain\User\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\User\Data\UserData;
use Domain\User\Enums\Role;
use Domain\User\Models\User;

class CreateCompanyUserAction extends AbstractAction
{
    /**
     * Add a seat to a company. The password is left null on purpose: the user
     * sets it via the emailed invite link (the caller sends the invite).
     */
    public function execute(int $companyId, string $name, string $email, Role $role): UserData
    {
        $user = User::create([
            'company_id' => $companyId,
            'full_name' => $name,
            'email' => $email,
            'role' => $role->value,
            'password' => null,
        ]);

        return $user->getData();
    }
}
