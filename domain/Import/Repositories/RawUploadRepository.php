<?php

declare(strict_types=1);

namespace Domain\Import\Repositories;

use Domain\Import\Data\RawUploadData;
use Domain\Import\Models\RawUpload;
use Illuminate\Support\Collection;

class RawUploadRepository
{
    public function find(int $id): ?RawUploadData
    {
        return RawUpload::find($id)?->getData();
    }

    public function findByUuid(string $uuid): ?RawUploadData
    {
        return RawUpload::where('uuid', $uuid)->first()?->getData();
    }

    public function recent(int $limit = 20): Collection
    {
        return RawUpload::orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn (RawUpload $upload) => $upload->getData());
    }

    public function forSupplier(int $supplierId): Collection
    {
        return RawUpload::where('supplier_id', $supplierId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (RawUpload $upload) => $upload->getData());
    }
}
