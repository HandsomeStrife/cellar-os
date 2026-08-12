<?php

declare(strict_types=1);

namespace Domain\Company\Actions;

use Domain\Company\Data\CompanyData;
use Domain\Company\Models\Company;
use Domain\Shared\Actions\AbstractAction;

/**
 * Forget that a company was ever on a trial.
 *
 * Used when a subscription goes active: the trial has served its purpose, and
 * a date left lying in the past becomes load-bearing again the moment Stripe
 * marks a card `past_due`, demoting a customer who has been paying for months.
 */
class ClearCompanyTrialAction extends AbstractAction
{
    public function execute(int $companyId): CompanyData
    {
        $company = Company::findOrFail($companyId);
        $company->update(['trial_ends_at' => null]);

        return $company->fresh()->getData();
    }
}
