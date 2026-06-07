<?php

declare(strict_types=1);

namespace Domain\User\Repositories;

use Domain\User\Data\UserData;
use Domain\User\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class UserRepository
{
    public function paginate(?string $term = null, int $perPage = 20): LengthAwarePaginator
    {
        return User::query()
            ->when($term !== null && $term !== '', function ($query) use ($term) {
                $query->where(function ($query) use ($term) {
                    $query->where('full_name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->through(fn (User $user) => $user->getData());
    }

    public function count(): int
    {
        return User::count();
    }

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

    public function findByStripeId(string $stripeId): ?UserData
    {
        return User::where('stripe_id', $stripeId)->first()?->getData();
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
