<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\Supplier\Data\SupplierData;
use Domain\Supplier\Models\Supplier;

class MarkSupplierOnboardedAction extends AbstractAction
{
    /**
     * Mark a supplier as onboarded (it has a portal account and self-manages).
     * Onboarded suppliers are always public. Admin-only.
     */
    public function execute(int $supplierId): SupplierData
    {
        $supplier = Supplier::findOrFail($supplierId);
        $supplier->update([
            'created_by_company_id' => null,
            'onboarded_at' => now(),
        ]);

        return $supplier->getData();
    }
}
