<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\Supplier\Data\SupplierData;
use Domain\Supplier\Enums\SupplierStatus;
use Domain\Supplier\Models\Supplier;

class ToggleSupplierStatusAction extends AbstractAction
{
    public function execute(int $id): SupplierData
    {
        $supplier = Supplier::findOrFail($id);

        $supplier->update([
            'status' => $supplier->status === SupplierStatus::Active
                ? SupplierStatus::Inactive
                : SupplierStatus::Active,
        ]);

        return $supplier->getData();
    }
}
