<?php

declare(strict_types=1);

namespace Domain\User\Actions;

use Domain\Billing\Enums\Plan;
use Domain\Shared\Actions\AbstractAction;
use Domain\User\Data\UserData;
use Domain\User\Models\User;

class SetUserPlanAction extends AbstractAction
{
    public function execute(int $userId, Plan $plan): UserData
    {
        $user = User::findOrFail($userId);
        $user->update(['plan' => $plan->value]);

        return $user->getData();
    }
}
