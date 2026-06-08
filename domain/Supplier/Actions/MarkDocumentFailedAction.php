<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\Supplier\Data\SupplierDocumentData;
use Domain\Supplier\Enums\SupplierDocumentStatus;
use Domain\Supplier\Models\SupplierDocument;

class MarkDocumentFailedAction extends AbstractAction
{
    public function execute(int $id, string $note): SupplierDocumentData
    {
        $document = SupplierDocument::findOrFail($id);

        $document->update([
            'status' => SupplierDocumentStatus::Failed,
            'analysis_notes' => $note,
            'analysed_at' => now(),
        ]);

        return $document->getData();
    }
}
