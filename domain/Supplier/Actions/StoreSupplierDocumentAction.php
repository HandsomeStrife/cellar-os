<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\Supplier\Data\SupplierDocumentData;
use Domain\Supplier\Enums\SupplierDocumentStatus;
use Domain\Supplier\Models\SupplierDocument;

class StoreSupplierDocumentAction extends AbstractAction
{
    /**
     * Record an uploaded portfolio / price sheet. The file itself is stored by
     * the caller (app layer) on the private disk; we only record where it lives
     * and start it at AwaitingAnalysis.
     */
    public function execute(
        int $supplierId,
        ?int $uploadedBySupplierUserId,
        ?string $title,
        string $fileName,
        ?string $fileType,
        int $fileSize,
        string $storagePath,
    ): SupplierDocumentData {
        $document = SupplierDocument::create([
            'supplier_id' => $supplierId,
            'uploaded_by_supplier_user_id' => $uploadedBySupplierUserId,
            'title' => $title,
            'file_name' => $fileName,
            'file_type' => $fileType,
            'file_size' => $fileSize,
            'storage_path' => $storagePath,
            'status' => SupplierDocumentStatus::AwaitingAnalysis,
        ]);

        return $document->getData();
    }
}
