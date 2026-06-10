<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\Supplier\Models\SupplierNote;

class DeleteSupplierNoteAction extends AbstractAction
{
    public function execute(int $noteId): void
    {
        SupplierNote::where('id', $noteId)->delete();
    }
}
