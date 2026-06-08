<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\Supplier\Models\Supplier;
use Domain\Supplier\Models\SupplierDocument;
use Illuminate\Support\Facades\Storage;

class DeleteSupplierAction extends AbstractAction
{
    public function execute(int $id): void
    {
        // Remove backing files before the DB cascade drops the document rows,
        // otherwise the uploaded files are orphaned on the private disk.
        SupplierDocument::where('supplier_id', $id)
            ->get(['storage_path'])
            ->each(function (SupplierDocument $document) {
                if ($document->storage_path && Storage::disk('local')->exists($document->storage_path)) {
                    Storage::disk('local')->delete($document->storage_path);
                }
            });

        Supplier::findOrFail($id)->delete();
    }
}
