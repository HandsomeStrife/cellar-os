<?php

declare(strict_types=1);

namespace Domain\Supplier\Repositories;

use Domain\Supplier\Data\SupplierDocumentData;
use Domain\Supplier\Enums\SupplierDocumentStatus;
use Domain\Supplier\Models\SupplierDocument;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SupplierDocumentRepository
{
    public function find(int $id): ?SupplierDocumentData
    {
        return SupplierDocument::find($id)?->getData();
    }

    public function findByUuid(string $uuid): ?SupplierDocumentData
    {
        return SupplierDocument::where('uuid', $uuid)->first()?->getData();
    }

    /**
     * @return Collection<int, SupplierDocumentData>
     */
    public function forSupplier(int $supplierId): Collection
    {
        return SupplierDocument::where('supplier_id', $supplierId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (SupplierDocument $document) => $document->getData());
    }

    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return SupplierDocument::query()
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->through(fn (SupplierDocument $document) => $document->getData());
    }

    public function countAwaitingAnalysis(): int
    {
        return SupplierDocument::where('status', SupplierDocumentStatus::AwaitingAnalysis->value)->count();
    }

    public function countForSupplier(int $supplierId): int
    {
        return SupplierDocument::where('supplier_id', $supplierId)->count();
    }
}
