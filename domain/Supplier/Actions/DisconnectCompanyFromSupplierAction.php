<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;
use Illuminate\Support\Facades\DB;

class DisconnectCompanyFromSupplierAction extends AbstractAction
{
    /**
     * Remove a supplier from a company's list, and drop any allocations of that
     * supplier to the company's venues. Other companies are unaffected.
     *
     * @param  array<int, int>  $companyVenueIds
     */
    public function execute(int $companyId, int $supplierId, array $companyVenueIds = []): void
    {
        DB::transaction(function () use ($companyId, $supplierId, $companyVenueIds) {
            DB::table('company_supplier')
                ->where('company_id', $companyId)
                ->where('supplier_id', $supplierId)
                ->delete();

            if ($companyVenueIds !== []) {
                DB::table('supplier_venue')
                    ->where('supplier_id', $supplierId)
                    ->whereIn('venue_id', $companyVenueIds)
                    ->delete();
            }
        });
    }
}
