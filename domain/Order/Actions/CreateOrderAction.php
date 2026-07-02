<?php

declare(strict_types=1);

namespace Domain\Order\Actions;

use Domain\Order\Data\OrderData;
use Domain\Order\Models\Order;
use Domain\Shared\Actions\AbstractAction;
use Illuminate\Support\Facades\DB;

class CreateOrderAction extends AbstractAction
{
    public function execute(OrderData $data): OrderData
    {
        return DB::transaction(function () use ($data) {
            $order = Order::create([
                'po_number' => $this->nextPoNumber($data->company_id),
                'company_id' => $data->company_id,
                'supplier_id' => $data->supplier_id,
                'venue_id' => $data->venue_id,
                'created_by' => $data->created_by,
                'status' => $data->status,
                'notes' => $data->notes,
                'total' => 0,
            ]);

            $total = 0.0;

            foreach ($data->items as $item) {
                $order->items()->create([
                    'product_id' => $item->product_id,
                    'wine_name' => $item->wine_name,
                    'quantity_units' => $item->quantity_units,
                    'sold_by_at_order' => $item->sold_by_at_order,
                    'pack_size_at_order' => $item->pack_size_at_order,
                    'unit_price_at_order' => $item->unit_price_at_order,
                    'pack_price_at_order' => $item->pack_price_at_order,
                    'currency_at_order' => $item->currency_at_order,
                ]);

                $total += $item->quantity_units * (float) $item->unit_price_at_order;
            }

            $order->update(['total' => $total]);

            return $order->fresh('items')->getData();
        });
    }

    /**
     * Next sequential PO number for the company, per year (PO-2026-0042).
     * Zero-padded so string ordering matches numeric ordering; the row lock
     * keeps concurrent creations from taking the same number. Numbers are
     * never reused — deletions leave gaps, as trade convention expects.
     */
    private function nextPoNumber(?int $companyId): string
    {
        $year = now()->format('Y');
        $prefix = "PO-{$year}-";

        $latest = Order::query()
            ->when($companyId === null, fn ($query) => $query->whereNull('company_id'))
            ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
            ->where('po_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('po_number')
            ->value('po_number');

        $sequence = $latest !== null ? (int) substr($latest, strlen($prefix)) + 1 : 1;

        return sprintf('%s%04d', $prefix, $sequence);
    }
}
