<?php

declare(strict_types=1);

namespace Domain\Order\Actions;

use Domain\Order\Models\Order;
use Domain\Shared\Actions\AbstractAction;

class DeleteOrderAction extends AbstractAction
{
    public function execute(int $id): void
    {
        // order_items cascade on delete (FK).
        Order::findOrFail($id)->delete();
    }
}
