<?php

declare(strict_types=1);

namespace Domain\Supplier\Repositories;

use Domain\Supplier\Data\SupplierNoteData;
use Domain\Supplier\Models\SupplierNote;
use Illuminate\Support\Collection;

class SupplierNoteRepository
{
    public function find(int $id): ?SupplierNoteData
    {
        return SupplierNote::find($id)?->getData();
    }

    /**
     * @return Collection<int, SupplierNoteData> newest first
     */
    public function forSupplier(int $supplierId): Collection
    {
        return SupplierNote::where('supplier_id', $supplierId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (SupplierNote $note) => $note->getData());
    }
}
