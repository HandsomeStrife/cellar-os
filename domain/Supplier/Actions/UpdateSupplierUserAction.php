<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\Supplier\Data\SupplierUserData;
use Domain\Supplier\Models\SupplierUser;

class UpdateSupplierUserAction extends AbstractAction
{
    public function execute(int $id, string $name, string $email): SupplierUserData
    {
        $user = SupplierUser::findOrFail($id);

        $user->update([
            'name' => $name,
            'email' => $email,
        ]);

        return $user->getData();
    }
}
