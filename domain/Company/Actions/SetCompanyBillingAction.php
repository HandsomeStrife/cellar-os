<?php

declare(strict_types=1);

namespace Domain\Company\Actions;

use Domain\Company\Data\CompanyBillingData;
use Domain\Company\Data\CompanyData;
use Domain\Company\Models\Company;
use Domain\Shared\Actions\AbstractAction;

/**
 * Put a company on its commercial terms: which plan, whether they pay, what
 * they pay, and how long a trial runs.
 *
 * One action rather than several because the fields constrain each other — a
 * custom price only means something alongside the arrangement that uses it,
 * and setting them apart invites the half-applied state where a company is on
 * list price but still carrying last quarter's discount.
 */
class SetCompanyBillingAction extends AbstractAction
{
    public function execute(int $companyId, CompanyBillingData $data): CompanyData
    {
        $company = Company::findOrFail($companyId);
        $terms = $data->normalised();

        $company->update([
            'plan' => $terms->plan,
            'billing_arrangement' => $terms->billing_arrangement,
            'custom_price_amount' => $terms->custom_price_amount,
            'custom_price_currency' => $terms->custom_price_currency,
            'custom_price_interval' => $terms->custom_price_interval,
            'billing_notes' => $terms->billing_notes,
            'trial_ends_at' => $terms->trial_ends_at,
        ]);

        return $company->fresh()->getData();
    }
}
