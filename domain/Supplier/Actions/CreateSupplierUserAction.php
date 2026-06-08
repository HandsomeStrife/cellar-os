<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\Supplier\Data\SupplierUserData;
use Domain\Supplier\Models\SupplierUser;

class CreateSupplierUserAction extends AbstractAction
{
    /**
     * Provision a supplier portal login. The password is left null on purpose:
     * the user sets it via the emailed invite link (the app layer sends a
     * password-reset notification on the `supplier_users` broker afterwards).
     */
    public function execute(int $supplierId, string $name, string $email): SupplierUserData
    {
        $user = SupplierUser::create([
            'supplier_id' => $supplierId,
            'name' => $name,
            'email' => $email,
            'password' => null,
        ]);

        return $user->getData();
    }
}
