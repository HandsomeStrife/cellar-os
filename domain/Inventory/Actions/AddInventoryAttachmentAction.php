<?php

declare(strict_types=1);

namespace Domain\Inventory\Actions;

use Domain\Inventory\Data\InventoryAttachmentData;
use Domain\Inventory\Models\InventoryAttachment;
use Domain\Shared\Actions\AbstractAction;

class AddInventoryAttachmentAction extends AbstractAction
{
    /**
     * Persist attachment metadata. The file itself is stored by the caller
     * (app layer) on a private disk; we only record where it lives.
     */
    public function execute(
        int $inventoryItemId,
        ?int $uploadedBy,
        string $fileName,
        string $fileType,
        int $fileSize,
        string $storagePath,
    ): InventoryAttachmentData {
        $attachment = InventoryAttachment::create([
            'inventory_item_id' => $inventoryItemId,
            'uploaded_by' => $uploadedBy,
            'file_name' => $fileName,
            'file_type' => $fileType,
            'file_size' => $fileSize,
            'storage_path' => $storagePath,
        ]);

        return $attachment->getData();
    }
}
