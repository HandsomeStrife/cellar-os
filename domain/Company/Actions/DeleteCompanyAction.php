<?php

declare(strict_types=1);

namespace Domain\Company\Actions;

use Domain\Company\Models\Company;
use Domain\Shared\Actions\AbstractAction;

class DeleteCompanyAction extends AbstractAction
{
    /**
     * Tear down a tenant. Cancels any active Stripe subscription, then deletes
     * the company — the DB cascades its users and venues (and their data).
     */
    public function execute(int $id): void
    {
        $company = Company::findOrFail($id);

        if ($company->subscribed('default')) {
            try {
                $company->subscription('default')->cancelNow();
            } catch (\Throwable) {
                // Stripe unreachable/unconfigured — proceed with deletion anyway.
            }
        }

        $company->delete();
    }
}
