<?php

declare(strict_types=1);

namespace Domain\Order\Actions;

use Domain\Order\Data\OrderData;
use Domain\Order\Enums\OrderStatus;
use Domain\Order\Models\Order;
use Domain\Shared\Actions\AbstractAction;

class UpdateOrderStatusAction extends AbstractAction
{
    public function execute(int $id, OrderStatus $status): OrderData
    {
        $order = Order::findOrFail($id);
        $order->update(['status' => $status]);

        return $order->fresh('items')->getData();
    }
}
