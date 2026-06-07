<?php

declare(strict_types=1);

namespace Domain\Inventory\Actions;

use Domain\Inventory\Models\InventoryAttachment;
use Domain\Shared\Actions\AbstractAction;

class DeleteInventoryAttachmentAction extends AbstractAction
{
    /**
     * Deletes the metadata row and returns the storage path so the caller
     * (app layer) can remove the underlying file from disk.
     */
    public function execute(int $id): string
    {
        $attachment = InventoryAttachment::findOrFail($id);
        $path = $attachment->storage_path;
        $attachment->delete();

        return $path;
    }
}
