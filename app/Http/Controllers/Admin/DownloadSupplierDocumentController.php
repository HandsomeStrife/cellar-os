<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Domain\Supplier\Repositories\SupplierDocumentRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadSupplierDocumentController
{
    public function __invoke(int $id): StreamedResponse
    {
        abort_unless(Auth::guard('admin')->check(), 403);

        $document = (new SupplierDocumentRepository)->find($id);
        abort_if($document === null, 404);

        abort_unless(Storage::disk('local')->exists($document->storage_path), 404);

        return Storage::disk('local')->download($document->storage_path, $document->file_name);
    }
}
