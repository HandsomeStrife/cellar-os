<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\Supplier\Models\SupplierUser;

class DeleteSupplierUserAction extends AbstractAction
{
    public function execute(int $id): void
    {
        SupplierUser::findOrFail($id)->delete();
    }
}
