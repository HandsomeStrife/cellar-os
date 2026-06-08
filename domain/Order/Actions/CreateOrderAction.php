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
                    'unit_price_at_order' => $item->unit_price_at_order,
                    'currency_at_order' => $item->currency_at_order,
                ]);

                $total += $item->quantity_units * (float) $item->unit_price_at_order;
            }

            $order->update(['total' => $total]);

            return $order->fresh('items')->getData();
        });
    }
}
