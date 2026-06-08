<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\Supplier\Data\SupplierData;
use Domain\Supplier\Models\Supplier;

class MakeSupplierPublicAction extends AbstractAction
{
    /**
     * Promote a private (buyer-added) supplier to a public Listed one, so it
     * becomes discoverable by every company. Admin-only.
     */
    public function execute(int $supplierId): SupplierData
    {
        $supplier = Supplier::findOrFail($supplierId);
        $supplier->update(['created_by_company_id' => null]);

        return $supplier->getData();
    }
}
