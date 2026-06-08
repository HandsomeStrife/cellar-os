<?php

declare(strict_types=1);

namespace Domain\Supplier\Repositories;

use Domain\Supplier\Data\SupplierData;
use Domain\Supplier\Models\Supplier;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SupplierRepository
{
    /**
     * The suppliers a company works with: ones it created (private) plus ones
     * it has connected to (the company_supplier pivot).
     *
     * @return Collection<int, SupplierData>
     */
    public function connectedToCompany(int $companyId, ?string $term = null): Collection
    {
        return Supplier::query()
            ->where(function ($query) use ($companyId) {
                $query->where('created_by_company_id', $companyId)
                    ->orWhereIn('id', DB::table('company_supplier')
                        ->where('company_id', $companyId)
                        ->select('supplier_id'));
            })
            ->when($term !== null && $term !== '', fn ($q) => $q->where('name', 'like', "%{$term}%"))
            ->orderBy('name')
            ->get()
            ->map(fn (Supplier $supplier) => $supplier->getData());
    }

    /**
     * Public suppliers (listed/onboarded) the company hasn't connected to yet —
     * the discovery list.
     *
     * @return Collection<int, SupplierData>
     */
    public function discoverableForCompany(int $companyId, ?string $term = null): Collection
    {
        return Supplier::query()
            ->whereNull('created_by_company_id')
            ->whereNotIn('id', DB::table('company_supplier')
                ->where('company_id', $companyId)
                ->select('supplier_id'))
            ->when($term !== null && $term !== '', fn ($q) => $q->where('name', 'like', "%{$term}%"))
            ->orderBy('name')
            ->get()
            ->map(fn (Supplier $supplier) => $supplier->getData());
    }

    public function isConnectedToCompany(int $supplierId, int $companyId): bool
    {
        $supplier = Supplier::find($supplierId);

        if ($supplier === null) {
            return false;
        }

        return $supplier->created_by_company_id === $companyId
            || DB::table('company_supplier')
                ->where('company_id', $companyId)
                ->where('supplier_id', $supplierId)
                ->exists();
    }

    /**
     * Venue ids a supplier is allocated to (the supplier_venue pivot).
     *
     * @return array<int, int>
     */
    public function venueIdsForSupplier(int $supplierId): array
    {
        return DB::table('supplier_venue')
            ->where('supplier_id', $supplierId)
            ->pluck('venue_id')
            ->all();
    }

    public function find(int $id): ?SupplierData
    {
        return Supplier::find($id)?->getData();
    }

    public function paginate(?string $term = null, int $perPage = 20): LengthAwarePaginator
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
            ->paginate($perPage)
            ->through(fn (Supplier $supplier) => $supplier->getData());
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
