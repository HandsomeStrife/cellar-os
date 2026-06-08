<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Domain\Billing\Enums\Plan;
use Domain\Company\Data\CompanyData;
use Domain\Company\Repositories\CompanyRepository;
use Domain\User\Data\UserData;
use Domain\User\Repositories\UserRepository;
use Domain\Venue\Data\VenueData;
use Domain\Venue\Repositories\VenueRepository;
use Illuminate\Support\Collection;

/**
 * Tenant resolution for the `web` (buyer) area: the current user, their company,
 * the company's plan (for feature gating) and the venues the user may act on.
 *
 * Venue access is role-aware: owners/managers see every company venue; members
 * see only the venues they're assigned to (the user_venue pivot).
 */
trait WithTenant
{
    protected function currentUser(): ?UserData
    {
        return (new UserRepository)->getLoggedInUser();
    }

    protected function currentCompany(): ?CompanyData
    {
        return (new CompanyRepository)->getLoggedInCompany();
    }

    protected function companyPlan(): Plan
    {
        return $this->currentCompany()?->plan ?? Plan::Free;
    }

    /**
     * @return Collection<int, VenueData>
     */
    protected function accessibleVenues(): Collection
    {
        $user = $this->currentUser();

        if ($user === null || $user->company_id === null) {
            return collect();
        }

        $venues = new VenueRepository;

        return $user->role->seesAllVenues()
            ? $venues->getForCompany($user->company_id)
            : $venues->getAssignedToUser($user->id);
    }

    /**
     * @return array<int, int>
     */
    protected function accessibleVenueIds(): array
    {
        return $this->accessibleVenues()->pluck('id')->all();
    }
}
