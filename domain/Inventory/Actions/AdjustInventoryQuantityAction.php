<?php

declare(strict_types=1);

namespace Domain\Inventory\Actions;

use Domain\Inventory\Data\InventoryItemData;
use Domain\Inventory\Models\InventoryItem;
use Domain\Shared\Actions\AbstractAction;

class AdjustInventoryQuantityAction extends AbstractAction
{
    public function execute(int $id, int $quantity): InventoryItemData
    {
        $item = InventoryItem::findOrFail($id);
        $item->update(['quantity_units' => max(0, $quantity)]);

        return $item->getData();
    }
}
