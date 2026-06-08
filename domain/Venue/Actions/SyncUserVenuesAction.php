<?php

declare(strict_types=1);

namespace Domain\Venue\Actions;

use Domain\Shared\Actions\AbstractAction;
use Illuminate\Support\Facades\DB;

class SyncUserVenuesAction extends AbstractAction
{
    /**
     * Replace a user's venue assignments (the user_venue pivot) with the given
     * set. Operates on the pivot table directly to avoid a cross-context relation.
     *
     * @param  array<int, int>  $venueIds
     */
    public function execute(int $userId, array $venueIds): void
    {
        DB::transaction(function () use ($userId, $venueIds) {
            DB::table('user_venue')->where('user_id', $userId)->delete();

            $now = now();
            $rows = collect($venueIds)
                ->unique()
                ->map(fn (int $venueId) => [
                    'user_id' => $userId,
                    'venue_id' => $venueId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all();

            if ($rows !== []) {
                DB::table('user_venue')->insert($rows);
            }
        });
    }
}
