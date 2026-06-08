<?php

declare(strict_types=1);

namespace App\Http\Controllers\SupplierPortal;

use Domain\Supplier\Repositories\SupplierDocumentRepository;
use Domain\Supplier\Repositories\SupplierUserRepository;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadDocumentController
{
    public function __invoke(int $id): StreamedResponse
    {
        $document = (new SupplierDocumentRepository)->find($id);
        abort_if($document === null, 404);

        // Authorize: the document must belong to the signed-in user's supplier.
        $supplierId = (new SupplierUserRepository)->getLoggedInSupplierUser()?->supplier_id;
        abort_unless($supplierId !== null && $document->supplier_id === $supplierId, 403);

        abort_unless(Storage::disk('local')->exists($document->storage_path), 404);

        return Storage::disk('local')->download($document->storage_path, $document->file_name);
    }
}
