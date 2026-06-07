<?php

declare(strict_types=1);

namespace Domain\Inventory\Repositories;

use Domain\Inventory\Data\InventoryAttachmentData;
use Domain\Inventory\Models\InventoryAttachment;

class InventoryAttachmentRepository
{
    public function find(int $id): ?InventoryAttachmentData
    {
        return InventoryAttachment::find($id)?->getData();
    }
}
