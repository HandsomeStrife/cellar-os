<?php

declare(strict_types=1);

namespace Domain\Import\Actions;

use Domain\Import\Data\RawUploadData;
use Domain\Import\Models\RawUpload;
use Domain\Shared\Actions\AbstractAction;

class StoreRawUploadAction extends AbstractAction
{
    /**
     * @param  array<int, array<string, string>>  $rows  associative rows keyed by header
     */
    public function execute(
        ?int $supplierId,
        ?int $uploadedBy,
        string $fileName,
        string $fileType,
        array $rows,
    ): RawUploadData {
        $upload = RawUpload::create([
            'supplier_id' => $supplierId,
            'uploaded_by' => $uploadedBy,
            'file_name' => $fileName,
            'file_type' => $fileType,
            'row_count' => count($rows),
            'rows' => $rows,
            'status' => 'pending',
        ]);

        return $upload->getData();
    }
}
