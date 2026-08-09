<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Domain\Catalogue\Enums\PriceState;
use Domain\Catalogue\Models\Product;
use Domain\Supplier\Actions\AddSupplierNoteAction;
use Domain\Supplier\Models\Supplier;
use Illuminate\Console\Command;

/**
 * Policy enforcement: we do not carry catalogue data without a price. This
 * archives (never deletes) any active product that has no positive unit_price
 * — typically the residue of an unpriced list ingested before the policy. The
 * wines are kept for history and un-archive automatically if a priced edition
 * later reintroduces them; meanwhile we source a priced list from the supplier.
 *
 * Wines the supplier has deliberately marked POA/TBC are NOT price-less — the
 * supplier told us the price is on application, which is information the buyer
 * needs. Those stay in the catalogue (see Domain\Catalogue\Enums\PriceState).
 *
 * Idempotent. archived_at travels with golden, so prod mirrors the cleanup.
 */
class ArchivePricelessWines extends Command
{
    protected $signature = 'wine:archive-priceless {--dry-run : report only, change nothing}';

    protected $description = 'Archive active catalogue wines that have no price (unpriced-data policy).';

    public function handle(): int
    {
        $priceless = fn ($query) => $query
            ->whereNull('archived_at')
            ->where('price_state', PriceState::Priced->value)
            ->where(fn ($q) => $q->whereNull('unit_price')->orWhere('unit_price', '<=', 0));

        $base = $priceless(Product::query());

        $bySupplier = (clone $base)
            ->selectRaw('supplier_id, count(*) c')
            ->groupBy('supplier_id')
            ->orderByDesc('c')
            ->get();

        if ($bySupplier->isEmpty()) {
            $this->info('No price-less active wines — catalogue is clean.');

            return self::SUCCESS;
        }

        $total = (int) $bySupplier->sum('c');
        $this->warn(($this->option('dry-run') ? '[dry run] ' : '')."Price-less active wines: {$total}");

        foreach ($bySupplier as $row) {
            $supplier = Supplier::find($row->supplier_id);
            $name = $supplier?->name ?? "supplier #{$row->supplier_id}";
            $this->line("  {$name}: {$row->c}");

            if ($this->option('dry-run')) {
                continue;
            }

            $priceless(Product::where('supplier_id', $row->supplier_id))
                ->update(['archived_at' => now()]);

            if ($supplier !== null) {
                (new AddSupplierNoteAction)->execute(
                    $supplier->id,
                    "Archived {$row->c} price-less wine(s) — we don't carry catalogue data without prices. "
                        .'Source a priced list from this supplier directly to restore them.',
                );
            }
        }

        return self::SUCCESS;
    }
}
