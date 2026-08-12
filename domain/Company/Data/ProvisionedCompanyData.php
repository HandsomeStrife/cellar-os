<?php

declare(strict_types=1);

namespace Domain\Company\Data;

use Domain\Shared\Data\AbstractData;
use Domain\User\Data\UserData;
use Domain\Venue\Data\VenueData;

/**
 * What provisioning produced. The owner comes back so the caller can send the
 * invite AFTER the transaction commits — a mail server having a bad afternoon
 * should not roll back a company that was created correctly.
 */
class ProvisionedCompanyData extends AbstractData
{
    public function __construct(
        public CompanyData $company,
        public VenueData $venue,
        public ?UserData $owner = null,
    ) {}
}
