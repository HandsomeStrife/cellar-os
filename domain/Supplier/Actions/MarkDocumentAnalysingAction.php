<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\Supplier\Data\SupplierDocumentData;
use Domain\Supplier\Enums\SupplierDocumentStatus;
use Domain\Supplier\Models\SupplierDocument;

class MarkDocumentAnalysingAction extends AbstractAction
{
    public function execute(int $id): SupplierDocumentData
    {
        $document = SupplierDocument::findOrFail($id);

        $document->update([
            'status' => SupplierDocumentStatus::Analysing,
            'analysis_notes' => null,
            'analysed_at' => null,
        ]);

        return $document->getData();
    }
}
