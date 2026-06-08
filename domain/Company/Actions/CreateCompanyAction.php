<?php

declare(strict_types=1);

namespace Domain\Company\Actions;

use Domain\Company\Data\CompanyData;
use Domain\Company\Models\Company;
use Domain\Shared\Actions\AbstractAction;

class CreateCompanyAction extends AbstractAction
{
    public function execute(CompanyData $data): CompanyData
    {
        $company = Company::create([
            'name' => $data->name,
            'base_currency' => $data->base_currency,
            'plan' => $data->plan,
        ]);

        return $company->getData();
    }
}
