<?php

declare(strict_types=1);

namespace Domain\Supplier\Repositories;

use Domain\Supplier\Data\SupplierData;
use Domain\Supplier\Models\Supplier;
use Illuminate\Support\Collection;

class SupplierRepository
{
    public function find(int $id): ?SupplierData
    {
        return Supplier::find($id)?->getData();
    }

    public function findByUuid(string $uuid): ?SupplierData
    {
        return Supplier::where('uuid', $uuid)->first()?->getData();
    }

    public function all(): Collection
    {
        return Supplier::all()
            ->map(fn (Supplier $supplier) => $supplier->getData());
    }

    public function search(?string $term = null): Collection
    {
        return Supplier::query()
            ->when($term !== null && $term !== '', function ($query) use ($term) {
                $query->where(function ($query) use ($term) {
                    $query->where('name', 'like', "%{$term}%")
                        ->orWhere('contact', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('location', 'like', "%{$term}%");
                });
            })
            ->orderBy('name')
            ->get()
            ->map(fn (Supplier $supplier) => $supplier->getData());
    }

    public function getActive(): Collection
    {
        return Supplier::active()
            ->get()
            ->map(fn (Supplier $supplier) => $supplier->getData());
    }

    public function count(): int
    {
        return Supplier::count();
    }

    public function countActive(): int
    {
        return Supplier::active()->count();
    }
}
