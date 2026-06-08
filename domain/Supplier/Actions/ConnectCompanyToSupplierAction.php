<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;
use Illuminate\Support\Facades\DB;

class ConnectCompanyToSupplierAction extends AbstractAction
{
    /**
     * Add a supplier to a company's "My suppliers" list (idempotent).
     */
    public function execute(int $companyId, int $supplierId): void
    {
        DB::table('company_supplier')->insertOrIgnore([
            'company_id' => $companyId,
            'supplier_id' => $supplierId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
