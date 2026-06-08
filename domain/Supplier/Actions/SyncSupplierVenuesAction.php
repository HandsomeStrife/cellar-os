<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;
use Illuminate\Support\Facades\DB;

class SyncSupplierVenuesAction extends AbstractAction
{
    /**
     * Allocate a supplier to a company's venues. Only touches the supplier_venue
     * rows for the company's own venues ($allowedVenueIds), so allocations made
     * by other companies for the same (shared) supplier are left intact.
     *
     * @param  array<int, int>  $allowedVenueIds  the acting company's venue ids
     * @param  array<int, int>  $selectedVenueIds  the venues to allocate to
     */
    public function execute(int $supplierId, array $allowedVenueIds, array $selectedVenueIds): void
    {
        DB::transaction(function () use ($supplierId, $allowedVenueIds, $selectedVenueIds) {
            DB::table('supplier_venue')
                ->where('supplier_id', $supplierId)
                ->whereIn('venue_id', $allowedVenueIds)
                ->delete();

            $now = now();
            $rows = collect($selectedVenueIds)
                ->intersect($allowedVenueIds)
                ->unique()
                ->map(fn (int $venueId) => [
                    'supplier_id' => $supplierId,
                    'venue_id' => $venueId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->values()
                ->all();

            if ($rows !== []) {
                DB::table('supplier_venue')->insert($rows);
            }
        });
    }
}
