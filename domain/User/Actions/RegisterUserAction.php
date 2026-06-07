<?php

declare(strict_types=1);

namespace Domain\User\Actions;

use Domain\Billing\Enums\Plan;
use Domain\Shared\Actions\AbstractAction;
use Domain\User\Data\RegisterUserData;
use Domain\User\Data\UserData;
use Domain\User\Models\User;

class RegisterUserAction extends AbstractAction
{
    public function execute(RegisterUserData $data): UserData
    {
        $user = User::create([
            'full_name' => $data->full_name,
            'email' => $data->email,
            'password' => $data->password,
            'role' => 'user',
            'plan' => Plan::Free->value,
        ]);

        return $user->getData();
    }
}
