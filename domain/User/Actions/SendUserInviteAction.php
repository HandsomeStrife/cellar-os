<?php

declare(strict_types=1);

namespace Domain\User\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\User\Models\User;
use Domain\User\Notifications\UserInviteNotification;
use Illuminate\Support\Facades\Password;

class SendUserInviteAction extends AbstractAction
{
    /**
     * Email a new (or re-invited) seat a link to set their password, using the
     * end-user `users` password broker.
     */
    public function execute(int $userId, string $companyName): void
    {
        $user = User::findOrFail($userId);
        $token = Password::broker('users')->createToken($user);

        $user->notify(new UserInviteNotification($token, $companyName));
    }
}
