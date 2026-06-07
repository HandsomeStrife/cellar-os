<?php

declare(strict_types=1);

namespace Domain\User\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\User\Models\User;

class DeleteUserAction extends AbstractAction
{
    public function execute(int $id): void
    {
        $user = User::findOrFail($id);

        // Don't orphan an active Stripe subscription (keeps billing them).
        if ($user->subscribed('default')) {
            try {
                $user->subscription('default')->cancelNow();
            } catch (\Throwable) {
                // Stripe unreachable/unconfigured — proceed with deletion anyway.
            }
        }

        $user->delete();
    }
}
