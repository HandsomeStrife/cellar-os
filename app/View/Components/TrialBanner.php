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
 * something had broken. It stays quiet in every other case, which is nearly
 * all of them, and the tenant lookup is deferred until we know there is
 * something to say — this sits in the authenticated layout, so it runs on
 * every page of the app.
 */
class TrialBanner extends Component
{
    private bool $resolved = false;

    private ?CompanyData $company = null;

    public function company(): ?CompanyData
    {
        if (! $this->resolved) {
            $this->company = (new CompanyRepository)->getLoggedInCompany();
            $this->resolved = true;
        }

        return $this->company;
    }

    public function shouldRender(): bool
    {
        $company = $this->company();

        if ($company === null) {
            return false;
        }

        // Nothing to warn a comped or agreed-price company about: their access
        // doesn't hang off a subscription, so their trial dates are inert.
        if (! $company->billing_arrangement->dependsOnStripe()) {
            return false;
        }

        // Someone who has already subscribed is not on a trial, whatever a
        // leftover date says — UNLESS they're trialling a tier above the one
        // they pay for, which is a real trial with a real cliff at the end of
        // it, and losing venues unannounced is exactly what this exists to
        // prevent.
        if ($company->has_active_subscription === true && ! $company->trialsAboveSubscription()) {
            return false;
        }

        return $this->countingDown() || $this->downgraded();
    }

    /**
     * A live trial worth mentioning. A trial issued ON the entry tier confers
     * nothing to lose, so counting it down would promise a cliff that never
     * arrives and then vanish without explanation.
     */
    public function countingDown(): bool
    {
        $company = $this->company();

        return $company !== null
            && $company->onTrial()
            && $company->trialConfersAnything();
    }

    /**
     * Has an expired trial actually cost them something?
     */
    public function downgraded(): bool
    {
        $company = $this->company();

        return $company !== null
            && $company->trialExpired()
            && $company->entitlementReduced();
    }

    public function daysLeft(): int
    {
        return $this->company()?->trialDaysRemaining() ?? 0;
    }

    public function render(): View
    {
        return view('components.trial-banner', ['company' => $this->company()]);
    }
}
