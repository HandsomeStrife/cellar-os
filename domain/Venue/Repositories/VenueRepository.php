<?php

declare(strict_types=1);

namespace Domain\Venue\Repositories;

use Domain\Venue\Data\VenueData;
use Domain\Venue\Models\Venue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
     * Every venue owned by a company (owners/managers see all of these).
     *
     * @return Collection<int, VenueData>
     */
    public function getForCompany(int $companyId): Collection
    {
        return Venue::where('company_id', $companyId)
            ->get()
            ->map(fn (Venue $venue) => $venue->getData());
    }

    /**
     * Only the venues a user is explicitly assigned to (members are scoped here).
     *
     * @return Collection<int, VenueData>
     */
    public function getAssignedToUser(int $userId): Collection
    {
        // Query the pivot directly — the user_venue link spans the User context,
        // so we don't define a cross-context Eloquent relation for it.
        $venueIds = DB::table('user_venue')->where('user_id', $userId)->pluck('venue_id');

        return Venue::whereIn('id', $venueIds)
            ->get()
            ->map(fn (Venue $venue) => $venue->getData());
    }

    public function countForCompany(int $companyId): int
    {
        return Venue::where('company_id', $companyId)->count();
    }

    /**
     * A company's base currency (from its first venue), defaulting to GBP.
     */
    public function currencyForCompany(int $companyId): string
    {
        return Venue::where('company_id', $companyId)->value('base_currency') ?? 'GBP';
    }
}
