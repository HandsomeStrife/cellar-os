<?php

declare(strict_types=1);

namespace Domain\Venue\Repositories;

use Domain\Venue\Data\VenueData;
use Domain\Venue\Models\Venue;
use Illuminate\Support\Collection;

class VenueRepository
{
    public function find(int $id): ?VenueData
    {
        return Venue::find($id)?->getData();
    }

    public function findByUuid(string $uuid): ?VenueData
    {
        return Venue::where('uuid', $uuid)->first()?->getData();
    }

    /**
     * @return Collection<int, VenueData>
     */
    public function all(): Collection
    {
        return Venue::all()->map(fn (Venue $venue) => $venue->getData());
    }

    /**
     * @return Collection<int, VenueData>
     */
    public function getForUser(int $userId): Collection
    {
        return Venue::where('user_id', $userId)
            ->get()
            ->map(fn (Venue $venue) => $venue->getData());
    }
}
