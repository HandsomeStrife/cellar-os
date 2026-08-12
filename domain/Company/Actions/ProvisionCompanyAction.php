<?php

declare(strict_types=1);

namespace Domain\Company\Actions;

use Domain\Company\Data\CompanyData;
use Domain\Company\Data\ProvisionCompanyData;
use Domain\Company\Data\ProvisionedCompanyData;
use Domain\Shared\Actions\AbstractAction;
use Domain\User\Actions\CreateCompanyUserAction;
use Domain\User\Actions\RegisterUserAction;
use Domain\User\Enums\Role;
use Domain\Venue\Actions\CreateVenueAction;
use Domain\Venue\Actions\SyncUserVenuesAction;
use Domain\Venue\Data\VenueData;
use Illuminate\Support\Facades\DB;

/**
 * Stand up a tenant from the back office: company, its terms, a first venue,
 * and optionally its owner.
 *
 * This is the admin counterpart to {@see RegisterUserAction},
 * which does the same job for someone signing themselves up. Both go through
 * {@see CreateCompanyAction} and {@see CreateVenueAction} so a hand-made tenant
 * is indistinguishable from a self-serve one — a company with no venue, or an
 * owner with no venue access, is broken in ways that only surface later.
 *
 * The invite email is deliberately NOT sent here: the caller sends it once the
 * transaction has committed.
 */
class ProvisionCompanyAction extends AbstractAction
{
    public function execute(ProvisionCompanyData $data): ProvisionedCompanyData
    {
        return DB::transaction(function () use ($data) {
            // Same normalisation the update path uses, so a company can't be
            // BORN carrying a custom price its arrangement doesn't use.
            $terms = $data->billing->normalised();

            $company = (new CreateCompanyAction)->execute(new CompanyData(
                id: null,
                uuid: null,
                name: $data->name,
                base_currency: $data->base_currency,
                plan: $terms->plan,
                billing_arrangement: $terms->billing_arrangement,
                custom_price_amount: $terms->custom_price_amount,
                custom_price_currency: $terms->custom_price_currency,
                custom_price_interval: $terms->custom_price_interval,
                billing_notes: $terms->billing_notes,
                trial_ends_at: $terms->trial_ends_at,
            ));

            $venue = (new CreateVenueAction)->execute(new VenueData(
                id: null,
                uuid: null,
                company_id: $company->id,
                name: $data->venueName(),
                address: null,
                city: null,
                country: null,
                base_currency: $data->base_currency,
            ));

            $owner = null;

            if ($data->hasOwner()) {
                $owner = (new CreateCompanyUserAction)->execute(
                    $company->id,
                    (string) $data->owner_name,
                    (string) $data->owner_email,
                    Role::Owner,
                );

                (new SyncUserVenuesAction)->execute($owner->id, [$venue->id]);
            }

            return new ProvisionedCompanyData(
                company: $company,
                venue: $venue,
                owner: $owner,
            );
        });
    }
}
