<?php

declare(strict_types=1);

namespace Domain\Venue\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\Venue\Data\VenueData;
use Domain\Venue\Models\Venue;

class CreateVenueAction extends AbstractAction
{
    public function execute(VenueData $data): VenueData
    {
        $venue = Venue::create([
            'company_id' => $data->company_id,
            'name' => $data->name,
            'address' => $data->address,
            'city' => $data->city,
            'country' => $data->country,
            'base_currency' => $data->base_currency,
        ]);

        return $venue->getData();
    }
}
