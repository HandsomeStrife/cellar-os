<?php

declare(strict_types=1);

namespace Domain\Import\Actions;

use Domain\Import\Models\RawUpload;
use Domain\Shared\Actions\AbstractAction;

class MarkRawUploadImportedAction extends AbstractAction
{
    /**
     * @param  array<string, string>  $mapping
     */
    public function execute(int $id, array $mapping): void
    {
        $upload = RawUpload::findOrFail($id);
        $upload->update([
            'column_mapping' => $mapping,
            'status' => 'imported',
        ]);
    }
}
