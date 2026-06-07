<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\Supplier\Models\Supplier;

class DeleteSupplierAction extends AbstractAction
{
    public function execute(int $id): void
    {
        Supplier::findOrFail($id)->delete();
    }
}
