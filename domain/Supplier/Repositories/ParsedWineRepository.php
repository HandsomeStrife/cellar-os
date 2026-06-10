<?php

declare(strict_types=1);

namespace Domain\Supplier\Repositories;

use Domain\Supplier\Data\ParsedWineData;
use Domain\Supplier\Enums\ParsedWineStatus;
use Domain\Supplier\Models\ParsedWine;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ParsedWineRepository
{
    public function find(int $id): ?ParsedWineData
    {
        return ParsedWine::find($id)?->getData();
    }

    public function paginateForDocument(int $documentId, int $perPage = 50): LengthAwarePaginator
    {
        return ParsedWine::where('supplier_document_id', $documentId)
            ->orderByRaw("CASE status WHEN 'proposed' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END")
            ->orderBy('id')
            ->paginate($perPage)
            ->through(fn (ParsedWine $row) => $row->getData());
    }

    /**
     * A few approved wines, used as worked examples to refine the recipe.
     *
     * @return array<int, array<string, mixed>>
     */
    public function approvedExamples(int $documentId, int $limit = 5): array
    {
        return ParsedWine::where('supplier_document_id', $documentId)
            ->where('status', ParsedWineStatus::Approved->value)
            ->limit($limit)
            ->get()
            ->map(fn (ParsedWine $row) => $row->payload)
            ->all();
    }

    /**
     * @return Collection<int, ParsedWineData>
     */
    public function forDocument(int $documentId): Collection
    {
        return ParsedWine::where('supplier_document_id', $documentId)
            ->orderBy('id')
            ->get()
            ->map(fn (ParsedWine $row) => $row->getData());
    }

    /**
     * @return array<string, int> status value => count, for a document
     */
    public function countsForDocument(int $documentId): array
    {
        return ParsedWine::where('supplier_document_id', $documentId)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();
    }

    public function countForDocument(int $documentId, ?ParsedWineStatus $status = null): int
    {
        return ParsedWine::where('supplier_document_id', $documentId)
            ->when($status !== null, fn ($q) => $q->where('status', $status->value))
            ->count();
    }
}
