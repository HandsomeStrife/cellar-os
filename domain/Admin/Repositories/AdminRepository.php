<?php

declare(strict_types=1);

namespace Domain\Admin\Repositories;

use Domain\Admin\Data\AdminData;
use Domain\Admin\Models\Admin;
use Illuminate\Support\Facades\Auth;

class AdminRepository
{
    public function find(int $id): ?AdminData
    {
        return Admin::find($id)?->getData();
    }

    public function findByUuid(string $uuid): ?AdminData
    {
        return Admin::where('uuid', $uuid)->first()?->getData();
    }

    public function findByEmail(string $email): ?AdminData
    {
        return Admin::where('email', $email)->first()?->getData();
    }

    /**
     * The currently authenticated admin (via the `admin` guard), or null.
     *
     * Domain code MUST use this rather than calling Auth::guard('admin') directly.
     */
    public function getLoggedInAdmin(): ?AdminData
    {
        $admin = Auth::guard('admin')->user();

        return $admin instanceof Admin ? $admin->getData() : null;
    }
}
