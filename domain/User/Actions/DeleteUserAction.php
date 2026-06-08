<?php

declare(strict_types=1);

namespace Domain\User\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\User\Models\User;

class DeleteUserAction extends AbstractAction
{
    /**
     * Remove a single seat. Billing lives on the company, so deleting a user
     * never touches Stripe (tenant teardown is DeleteCompanyAction's job).
     */
    public function execute(int $id): void
    {
        User::findOrFail($id)->delete();
    }
}
