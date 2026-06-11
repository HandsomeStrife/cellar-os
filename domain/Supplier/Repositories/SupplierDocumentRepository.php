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

    /**
     * Documents visible to the supplier portal: only the supplier's OWN uploads
     * (never a buyer's private document about them).
     *
     * @return Collection<int, SupplierDocumentData>
     */
    public function forSupplierPortal(int $supplierId): Collection
    {
        return SupplierDocument::where('supplier_id', $supplierId)
            ->whereNull('uploaded_by_company_id')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (SupplierDocument $document) => $document->getData());
    }

    /**
     * A company's own uploaded documents for a given supplier (buyer side).
     *
     * @return Collection<int, SupplierDocumentData>
     */
    public function forSupplierAndCompany(int $supplierId, int $companyId): Collection
    {
        return SupplierDocument::where('supplier_id', $supplierId)
            ->where('uploaded_by_company_id', $companyId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (SupplierDocument $document) => $document->getData());
    }

    /**
     * The documents a scheduled refresh can re-download: current (not yet
     * superseded) and carrying the URL their file was originally fetched from.
     *
     * @return Collection<int, SupplierDocumentData>
     */
    public function refreshable(): Collection
    {
        return SupplierDocument::whereNotNull('source_url')
            ->whereNull('archived_at')
            ->orderBy('id')
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
