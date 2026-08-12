<?php

declare(strict_types=1);

namespace Domain\Company\Data;

use Domain\Shared\Data\AbstractData;

/**
 * Everything needed to stand up a new tenant from the back office: the company
 * itself, its commercial terms, its first venue, and optionally the person who
 * will own it.
 *
 * The owner is optional because the two happen at different speeds — a company
 * is often set up before anyone knows who at that company will hold the login.
 */
class ProvisionCompanyData extends AbstractData
{
    public function __construct(
        public string $name,
        public string $base_currency,
        public CompanyBillingData $billing,
        public ?string $venue_name = null,
        public ?string $owner_name = null,
        public ?string $owner_email = null,
    ) {}

    public function hasOwner(): bool
    {
        return $this->owner_name !== null
            && $this->owner_name !== ''
            && $this->owner_email !== null
            && $this->owner_email !== '';
    }

    /**
     * A company with no venue can't hold stock or raise an order, so one is
     * always created; the company's own name is the sensible default.
     */
    public function venueName(): string
    {
        return $this->venue_name !== null && $this->venue_name !== ''
            ? $this->venue_name
            : $this->name;
    }
}
