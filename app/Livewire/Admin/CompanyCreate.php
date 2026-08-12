<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Concerns\EditsCompanyBilling;
use Carbon\CarbonImmutable;
use Domain\Billing\Enums\BillingArrangement;
use Domain\Billing\Enums\Plan;
use Domain\Company\Actions\ProvisionCompanyAction;
use Domain\Company\Data\ProvisionCompanyData;
use Domain\Shared\Support\Currency;
use Domain\User\Actions\SendUserInviteAction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Set a tenant up by hand: the company, its terms, its first venue, and
 * optionally the person who will own it.
 *
 * This exists so a deal agreed on a call can be honoured the same afternoon,
 * without anyone touching the database or the customer having to self-serve
 * through a checkout that is currently switched off.
 */
#[Layout('layouts.admin')]
#[Title('New company')]
class CompanyCreate extends Component
{
    use EditsCompanyBilling;

    public string $name = '';

    public string $baseCurrency = 'GBP';

    public string $venueName = '';

    public string $plan = Plan::Pro->value;

    /** Days from today. Blank or 0 means no trial. */
    public string $trialDays = '';

    public string $ownerName = '';

    public string $ownerEmail = '';

    public bool $sendInvite = true;

    public function create()
    {
        abort_unless(Auth::guard('admin')->check(), 403);

        $arrangement = $this->currentArrangement();

        $this->validate($this->rules($arrangement), $this->messages());

        $trialDays = (int) $this->trialDays;

        $provisioned = (new ProvisionCompanyAction)->execute(new ProvisionCompanyData(
            name: $this->name,
            base_currency: strtoupper($this->baseCurrency),
            billing: $this->billingTerms(
                Plan::from($this->plan),
                $arrangement,
                $trialDays > 0 ? CarbonImmutable::now()->addDays($trialDays)->endOfDay() : null,
            ),
            venue_name: $this->venueName === '' ? null : $this->venueName,
            owner_name: $this->ownerName === '' ? null : $this->ownerName,
            owner_email: $this->ownerEmail === '' ? null : $this->ownerEmail,
        ));

        // Outside the action, so a mail failure can't undo a company that was
        // created correctly.
        if ($provisioned->owner !== null && $this->sendInvite) {
            (new SendUserInviteAction)->execute($provisioned->owner->id, $provisioned->company->name);
        }

        session()->flash('success', $provisioned->owner !== null && $this->sendInvite
            ? "{$provisioned->company->name} created, and {$provisioned->owner->email} invited."
            : "{$provisioned->company->name} created.");

        return $this->redirectRoute('admin.companies.show', ['uuid' => $provisioned->company->uuid], navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(BillingArrangement $arrangement): array
    {
        return [
            ...$this->billingRules($arrangement),
            'name' => ['required', 'string', 'max:255'],
            'baseCurrency' => ['required', Rule::in(array_keys(Currency::SYMBOLS))],
            'venueName' => ['nullable', 'string', 'max:255'],
            'trialDays' => ['nullable', 'integer', 'min:0', 'max:730'],
            // An owner is optional, but a name without an address (or the
            // reverse) is a half-filled form, not a decision.
            'ownerName' => ['nullable', 'string', 'max:255', 'required_with:ownerEmail'],
            'ownerEmail' => ['nullable', 'email', 'max:255', 'required_with:ownerName', 'unique:users,email'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            ...$this->billingMessages(),
            'ownerEmail.unique' => 'Someone already has an account with that address.',
            'ownerName.required_with' => 'Give the owner a name as well as an address.',
            'ownerEmail.required_with' => 'Give the owner an address as well as a name.',
        ];
    }

    public function render()
    {
        return view('livewire.admin.company-create', $this->billingOptions());
    }
}
