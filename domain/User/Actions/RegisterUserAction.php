<?php

declare(strict_types=1);

namespace Domain\User\Actions;

use Domain\Billing\Enums\Plan;
use Domain\Shared\Actions\AbstractAction;
use Domain\User\Data\RegisterUserData;
use Domain\User\Data\UserData;
use Domain\User\Models\User;
use Domain\User\Models\UserProfile;
use Domain\Venue\Actions\CreateVenueAction;
use Domain\Venue\Data\VenueData;
use Illuminate\Support\Facades\DB;

class RegisterUserAction extends AbstractAction
{
    public function execute(RegisterUserData $data): UserData
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'full_name' => $data->full_name,
                'email' => $data->email,
                'password' => $data->password,
                'role' => 'user',
                'plan' => Plan::Free->value,
            ]);

            if ($data->profession !== null || $data->company_name !== null) {
                UserProfile::create([
                    'user_id' => $user->id,
                    'profession' => $data->profession,
                    'company_name' => $data->company_name,
                ]);
            }

            // First venue (with the chosen base currency), atomically with the user.
            (new CreateVenueAction)->execute(new VenueData(
                id: null,
                uuid: null,
                user_id: $user->id,
                name: $data->company_name ?: 'My Venue',
                address: null,
                city: null,
                country: null,
                base_currency: $data->base_currency,
            ));

            return $user->getData();
        });
    }
}
