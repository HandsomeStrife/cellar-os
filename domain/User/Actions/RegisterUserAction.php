<?php

declare(strict_types=1);

namespace Domain\User\Actions;

use Domain\Billing\Enums\Plan;
use Domain\Company\Actions\CreateCompanyAction;
use Domain\Company\Data\CompanyData;
use Domain\Shared\Actions\AbstractAction;
use Domain\User\Data\RegisterUserData;
use Domain\User\Data\UserData;
use Domain\User\Enums\Role;
use Domain\User\Models\User;
use Domain\User\Models\UserProfile;
use Domain\Venue\Actions\CreateVenueAction;
use Domain\Venue\Actions\SyncUserVenuesAction;
use Domain\Venue\Data\VenueData;
use Illuminate\Support\Facades\DB;

class RegisterUserAction extends AbstractAction
{
    public function execute(RegisterUserData $data): UserData
    {
        return DB::transaction(function () use ($data) {
            // The registrant becomes the owner of a brand-new company (the tenant).
            $company = (new CreateCompanyAction)->execute(new CompanyData(
                id: null,
                uuid: null,
                name: $data->company_name ?: 'My Company',
                base_currency: $data->base_currency,
                plan: Plan::Free,
            ));

            $user = User::create([
                'company_id' => $company->id,
                'full_name' => $data->full_name,
                'email' => $data->email,
                'password' => $data->password,
                'role' => Role::Owner->value,
            ]);

            if ($data->profession !== null) {
                UserProfile::create([
                    'user_id' => $user->id,
                    'profession' => $data->profession,
                ]);
            }

            // First venue (with the chosen base currency); give the owner access to it.
            $venue = (new CreateVenueAction)->execute(new VenueData(
                id: null,
                uuid: null,
                company_id: $company->id,
                name: $data->company_name ?: 'My Venue',
                address: null,
                city: null,
                country: null,
                base_currency: $data->base_currency,
            ));

            (new SyncUserVenuesAction)->execute($user->id, [$venue->id]);

            return $user->getData();
        });
    }
}
