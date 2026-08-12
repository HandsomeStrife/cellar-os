<?php

declare(strict_types=1);

namespace App\View\Components;

use Domain\Company\Data\CompanyData;
use Domain\Company\Repositories\CompanyRepository;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Tells a company on a trial how long is left, and tells one whose trial has
 * run out why the app got smaller.
 *
 * A trial that expires in silence is how you lose a customer who thought
 * something had broken. Only shown where there is something to say: a company
 * that has never had a trial sees nothing at all.
 */
class TrialBanner extends Component
{
    public ?CompanyData $company = null;

    public function __construct()
    {
        $this->company = (new CompanyRepository)->getLoggedInCompany();
    }

    public function shouldRender(): bool
    {
        if ($this->company === null) {
            return false;
        }

        // A comped company's trial dates are irrelevant — they're not paying
        // either way, so there is nothing to warn them about.
        if ($this->company->billing_arrangement->isFree()) {
            return false;
        }

        return $this->company->onTrial() || $this->downgraded();
    }

    /**
     * Has an expired trial actually cost them something? If the trial was on
     * the entry tier there is nothing to have lost, and saying so would only
     * confuse.
     */
    public function downgraded(): bool
    {
        return $this->company !== null
            && $this->company->trialExpired()
            && $this->company->effectivePlan() !== $this->company->plan;
    }

    public function daysLeft(): int
    {
        return $this->company?->trialDaysRemaining() ?? 0;
    }

    public function render(): View
    {
        return view('components.trial-banner');
    }
}
