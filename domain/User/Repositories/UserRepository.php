<?php

declare(strict_types=1);

namespace Domain\User\Repositories;

use Domain\User\Data\UserData;
use Domain\User\Models\User;
use Illuminate\Support\Facades\Auth;

class UserRepository
{
    public function find(int $id): ?UserData
    {
        return User::find($id)?->getData();
    }

    public function findOrFail(int $id): UserData
    {
        return User::findOrFail($id)->getData();
    }

    public function findByEmail(string $email): ?UserData
    {
        return User::where('email', $email)->first()?->getData();
    }

    /**
     * The currently authenticated user, or null when unauthenticated.
     *
     * Domain code MUST use this rather than calling Auth::user() directly.
     */
    public function getLoggedInUser(): ?UserData
    {
        $user = Auth::user();

        return $user instanceof User ? $user->getData() : null;
    }
}
