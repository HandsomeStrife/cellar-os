<?php

declare(strict_types=1);

namespace Domain\Inventory\Actions;

use Domain\Inventory\Data\InventoryItemData;
use Domain\Inventory\Models\InventoryItem;
use Domain\Shared\Actions\AbstractAction;

class ArchiveInventoryItemAction extends AbstractAction
{
    public function execute(int $id): InventoryItemData
    {
        $item = InventoryItem::findOrFail($id);
        $item->update([
            'is_archived' => true,
            'archived_at' => now(),
        ]);

        return $item->getData();
    }
}
