<?php

declare(strict_types=1);

namespace Domain\Company\Actions;

use Domain\Billing\Enums\Plan;
use Domain\Company\Data\CompanyData;
use Domain\Company\Models\Company;
use Domain\Shared\Actions\AbstractAction;

class SetCompanyPlanAction extends AbstractAction
{
    public function execute(int $companyId, Plan $plan): CompanyData
    {
        $company = Company::findOrFail($companyId);
        $company->update(['plan' => $plan->value]);

        return $company->getData();
    }
}
