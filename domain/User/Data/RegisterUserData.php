<?php

declare(strict_types=1);

namespace Domain\User\Data;

use Domain\Shared\Data\AbstractData;

class RegisterUserData extends AbstractData
{
    public function __construct(
        public string $full_name,
        public string $email,
        public string $password,
        public ?string $profession = null,
        public ?string $company_name = null,
        public string $base_currency = 'GBP',
    ) {}
}
