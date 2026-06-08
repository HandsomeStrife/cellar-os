<?php

declare(strict_types=1);

namespace Domain\Supplier\Repositories;

use Domain\Supplier\Data\SupplierUserData;
use Domain\Supplier\Models\SupplierUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class SupplierUserRepository
{
    public function find(int $id): ?SupplierUserData
    {
        return SupplierUser::find($id)?->getData();
    }

    public function findByUuid(string $uuid): ?SupplierUserData
    {
        return SupplierUser::where('uuid', $uuid)->first()?->getData();
    }

    public function findByEmail(string $email): ?SupplierUserData
    {
        return SupplierUser::where('email', $email)->first()?->getData();
    }

    /**
     * @return Collection<int, SupplierUserData>
     */
    public function forSupplier(int $supplierId): Collection
    {
        return SupplierUser::where('supplier_id', $supplierId)
            ->orderBy('name')
            ->get()
            ->map(fn (SupplierUser $user) => $user->getData());
    }

    /**
     * The currently authenticated supplier user (via the `supplier` guard), or null.
     *
     * Domain code MUST use this rather than calling Auth::guard('supplier') directly.
     */
    public function getLoggedInSupplierUser(): ?SupplierUserData
    {
        $user = Auth::guard('supplier')->user();

        return $user instanceof SupplierUser ? $user->getData() : null;
    }
}
