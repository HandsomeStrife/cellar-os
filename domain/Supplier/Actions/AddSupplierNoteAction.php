<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\Supplier\Data\SupplierNoteData;
use Domain\Supplier\Models\SupplierNote;

class AddSupplierNoteAction extends AbstractAction
{
    public function execute(int $supplierId, string $note, ?int $adminId = null): SupplierNoteData
    {
        return SupplierNote::create([
            'supplier_id' => $supplierId,
            'admin_id' => $adminId,
            'note' => $note,
        ])->getData();
    }
}
