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
        ?int $uploadedByCompanyId = null,
        ?int $uploadedByUserId = null,
        ?string $sourceUrl = null,
        ?string $contentSha256 = null,
    ): SupplierDocumentData {
        $document = SupplierDocument::create([
            'supplier_id' => $supplierId,
            'uploaded_by_supplier_user_id' => $uploadedBySupplierUserId,
            'uploaded_by_company_id' => $uploadedByCompanyId,
            'uploaded_by_user_id' => $uploadedByUserId,
            'title' => $title,
            'file_name' => $fileName,
            'file_type' => $fileType,
            'file_size' => $fileSize,
            'storage_path' => $storagePath,
            // A source URL (with the stored copy's hash) enrols the document in
            // the weekly published-list refresh, which SHA-gates re-downloads.
            'source_url' => $sourceUrl,
            'content_sha256' => $contentSha256,
            'status' => SupplierDocumentStatus::AwaitingAnalysis,
        ]);

        return $document->getData();
    }
}
