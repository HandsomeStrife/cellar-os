<?php

declare(strict_types=1);

namespace App\Http\Controllers\Suppliers;

use Domain\Supplier\Repositories\SupplierDocumentRepository;
use Domain\User\Repositories\UserRepository;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadDocumentController
{
    public function __invoke(int $id): StreamedResponse
    {
        $document = (new SupplierDocumentRepository)->find($id);
        abort_if($document === null, 404);

        // Authorize: the document must have been uploaded by the current company.
        $companyId = (new UserRepository)->getLoggedInUser()?->company_id;
        abort_unless($companyId !== null && $document->uploaded_by_company_id === $companyId, 403);

        abort_unless(Storage::disk('local')->exists($document->storage_path), 404);

        return Storage::disk('local')->download($document->storage_path, $document->file_name);
    }
}
