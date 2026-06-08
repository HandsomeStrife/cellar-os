<?php

declare(strict_types=1);

namespace App\Livewire\Billing;

use Domain\Billing\Enums\Feature;
use Domain\Billing\Enums\Plan;
use Domain\Company\Models\Company;
use Domain\Company\Repositories\CompanyRepository;
use Domain\User\Repositories\UserRepository;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Pricing')]
class Pricing extends Component
{
    public function checkout(string $plan)
    {
        $planEnum = Plan::tryFrom($plan);
        abort_if($planEnum === null || ! $planEnum->isPaid(), 422);

        $priceId = $planEnum->stripePriceId();

        if (! $this->billingConfigured() || $priceId === null) {
            $this->dispatch('toast', message: 'Billing isn\'t configured yet — contact us to upgrade.');

            return null;
        }

        // Billing is owner-only; managers/members can't change the plan.
        abort_unless($this->canManageBilling(), 403);

        $company = $this->company();
        abort_if($company === null, 403);

        // Existing subscribers change plan in place — never open a second
        // Checkout (which would create a duplicate, double-billed subscription).
        if ($company->subscribed('default')) {
            $company->subscription('default')->swap($priceId);
            $this->dispatch('toast', message: 'Your plan has been updated.');

            return null;
        }

        return $company
            ->newSubscription('default', $priceId)
            ->checkout([
                'success_url' => route('pricing').'?checkout=success',
                'cancel_url' => route('pricing').'?checkout=cancelled',
            ]);
    }

    public function billingPortal()
    {
        abort_unless($this->canManageBilling(), 403);

        $company = $this->company();

        if ($company === null || ! $this->billingConfigured() || ! $company->hasStripeId()) {
            $this->dispatch('toast', message: 'You don\'t have a billing account yet.');

            return null;
        }

        return $company->redirectToBillingPortal(route('pricing'));
    }

    private function company(): ?Company
    {
        $companyId = auth()->user()?->company_id;

        return $companyId ? Company::find($companyId) : null;
    }

    private function canManageBilling(): bool
    {
        return (new UserRepository)->getLoggedInUser()?->role->canManageBilling() ?? false;
    }

    private function billingConfigured(): bool
    {
        return (bool) config('cashier.secret');
    }

    public function render()
    {
        return view('livewire.billing.pricing', [
            'plans' => [Plan::Free, ...Plan::paid()],
            'currentPlan' => (new CompanyRepository)->getLoggedInCompany()?->plan ?? Plan::Free,
            'unlockedAt' => fn (Plan $plan) => Feature::unlockedAt($plan),
            'billingConfigured' => $this->billingConfigured(),
            'canManageBilling' => $this->canManageBilling(),
        ]);
    }
}
