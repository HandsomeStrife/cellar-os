<?php

declare(strict_types=1);

namespace Domain\Supplier\Repositories;

use Domain\Supplier\Data\SupplierParseProfileData;
use Domain\Supplier\Enums\ParseMode;
use Domain\Supplier\Models\SupplierParseProfile;

class SupplierParseProfileRepository
{
    /**
     * The current active recipe for a supplier in the given mode (latest wins),
     * reused to prime the next parse. Null on the first-ever document.
     *
     * A buyer company sees its OWN profile first, falling back to the global
     * (portal/admin-learned) one; it never sees another company's. Portal/admin
     * callers (companyId null) only see global profiles.
     */
    public function activeForSupplier(int $supplierId, ParseMode $mode, ?int $companyId = null): ?SupplierParseProfileData
    {
        return SupplierParseProfile::where('supplier_id', $supplierId)
            ->where('mode', $mode->value)
            ->where('is_active', true)
            ->where(function ($query) use ($companyId) {
                $query->whereNull('company_id');

                if ($companyId !== null) {
                    $query->orWhere('company_id', $companyId);
                }
            })
            ->orderByRaw('CASE WHEN company_id IS NULL THEN 1 ELSE 0 END') // own profile beats global
            ->latest('id')
            ->first()
            ?->getData();
    }
}
