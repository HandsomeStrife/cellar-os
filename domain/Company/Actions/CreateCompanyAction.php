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
            'billing_arrangement' => $data->billing_arrangement,
            'custom_price_amount' => $data->custom_price_amount,
            'custom_price_currency' => $data->custom_price_currency,
            'custom_price_interval' => $data->custom_price_interval,
            'billing_notes' => $data->billing_notes,
            'trial_ends_at' => $data->trial_ends_at,
        ]);

        return $company->getData();
    }
}
