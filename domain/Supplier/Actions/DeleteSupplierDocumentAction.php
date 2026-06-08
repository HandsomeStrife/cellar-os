<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\Supplier\Models\SupplierDocument;
use Illuminate\Support\Facades\Storage;

class DeleteSupplierDocumentAction extends AbstractAction
{
    /**
     * Remove the record and its backing file from the private disk.
     */
    public function execute(int $id): void
    {
        $document = SupplierDocument::findOrFail($id);

        if ($document->storage_path && Storage::disk('local')->exists($document->storage_path)) {
            Storage::disk('local')->delete($document->storage_path);
        }

        $document->delete();
    }
}
