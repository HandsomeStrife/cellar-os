<?php

declare(strict_types=1);

namespace Domain\Catalogue\Repositories;

use Domain\Catalogue\Data\WineFactData;
use Domain\Catalogue\Models\WineFact;
use Illuminate\Support\Collection;

class WineFactRepository
{
    public function findByIdentity(string $identityKey): ?WineFactData
    {
        return WineFact::where('identity_key', $identityKey)->first()?->getData();
    }

    /**
     * @param  array<int, string>  $identityKeys
     * @return Collection<string, WineFactData> keyed by identity_key
     */
    public function forIdentities(array $identityKeys): Collection
    {
        if ($identityKeys === []) {
            return collect();
        }

        return WineFact::whereIn('identity_key', array_unique($identityKeys))
            ->get()
            ->mapWithKeys(fn (WineFact $fact) => [$fact->identity_key => $fact->getData()]);
    }

    public function count(): int
    {
        return WineFact::count();
    }
}
