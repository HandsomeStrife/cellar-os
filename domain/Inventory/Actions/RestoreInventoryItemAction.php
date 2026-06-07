<?php

declare(strict_types=1);

namespace Domain\Inventory\Actions;

use Domain\Inventory\Data\InventoryItemData;
use Domain\Inventory\Models\InventoryItem;
use Domain\Shared\Actions\AbstractAction;

class RestoreInventoryItemAction extends AbstractAction
{
    public function execute(int $id): InventoryItemData
    {
        $item = InventoryItem::findOrFail($id);
        $item->update([
            'is_archived' => false,
            'archived_at' => null,
        ]);

        return $item->getData();
    }
}
