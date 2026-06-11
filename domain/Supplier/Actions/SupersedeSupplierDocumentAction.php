<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\Supplier\Data\SupplierDocumentData;
use Domain\Supplier\Enums\SupplierDocumentStatus;
use Domain\Supplier\Models\SupplierDocument;

/**
 * Records a refreshed edition of a published supplier list: a new document
 * row becomes the active version and the old one is ARCHIVED — never deleted.
 * The old document keeps its file, parsed_wines and analysis notes as a
 * historical record, with `archived_at` set and `superseded_by_document_id`
 * pointing at its replacement.
 */
class SupersedeSupplierDocumentAction extends AbstractAction
{
    public function execute(
        SupplierDocumentData $old,
        string $fileName,
        string $storagePath,
        int $fileSize,
        string $sha256,
    ): SupplierDocumentData {
        $new = SupplierDocument::create([
            'supplier_id' => $old->supplier_id,
            'uploaded_by_supplier_user_id' => null,
            'uploaded_by_company_id' => $old->uploaded_by_company_id,
            'uploaded_by_user_id' => $old->uploaded_by_user_id,
            'title' => $old->title,
            'file_name' => $fileName,
            'file_type' => $old->file_type,
            'file_size' => $fileSize,
            'storage_path' => $storagePath,
            'source_url' => $old->source_url,
            'content_sha256' => $sha256,
            'status' => SupplierDocumentStatus::AwaitingAnalysis,
        ]);

        SupplierDocument::whereKey($old->id)->update([
            'archived_at' => now(),
            'superseded_by_document_id' => $new->id,
        ]);

        return $new->getData();
    }
}
