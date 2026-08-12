<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use Carbon\CarbonImmutable;
use Domain\Billing\Enums\BillingArrangement;
use Domain\Billing\Enums\BillingInterval;
use Domain\Billing\Enums\Plan;
use Domain\Company\Actions\DeleteCompanyAction;
use Domain\Company\Actions\SetCompanyBillingAction;
use Domain\Company\Data\CompanyBillingData;
use Domain\Company\Data\CompanyData;
use Domain\Company\Repositories\CompanyRepository;
use Domain\Shared\Support\Currency;
use Domain\Shared\Support\MoneyInput;
use Domain\User\Actions\CreateCompanyUserAction;
use Domain\User\Actions\DeleteUserAction;
use Domain\User\Actions\SendUserInviteAction;
use Domain\User\Data\UserData;
use Domain\User\Enums\Role;
use Domain\User\Repositories\UserRepository;
use Domain\Venue\Repositories\VenueRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Company')]
class CompanyShow extends Component
{
    public string $uuid = '';

    public ?int $companyId = null;

    public string $plan = Plan::Pro->value;

    public string $arrangement = BillingArrangement::Standard->value;

    /** Entered in whole currency units ("49.50"), stored in minor units. */
    public string $customPrice = '';

    public string $customCurrency = 'GBP';

    public string $customInterval = BillingInterval::Month->value;

    public string $billingNotes = '';

    /** Blank means no trial. Held as a date so it survives a page refresh. */
    public string $trialEndsAt = '';

    #[Validate('required|string|max:255')]
    public string $newUserName = '';

    #[Validate('required|email|max:255')]
    public string $newUserEmail = '';

    public string $newUserRole = Role::Member->value;

    public function mount(string $uuid): void
    {
        $company = (new CompanyRepository)->findByUuid($uuid);
        abort_if($company === null, 404);

        $this->uuid = $company->uuid;
        $this->companyId = $company->id;
        $this->fillFrom($company);
    }

    private function fillFrom(CompanyData $company): void
    {
        $this->plan = $company->plan->value;
        $this->arrangement = $company->billing_arrangement->value;
        $this->customPrice = $company->custom_price_amount === null
            ? ''
            : number_format($company->custom_price_amount / 100, 2, '.', '');
        $this->customCurrency = $company->custom_price_currency ?? $company->base_currency;
        $this->customInterval = ($company->custom_price_interval ?? BillingInterval::Month)->value;
        $this->billingNotes = (string) $company->billing_notes;
        $this->trialEndsAt = $company->trial_ends_at?->format('Y-m-d') ?? '';
    }

    /**
     * Plan, arrangement, price and trial are saved together: they constrain
     * one another, and saving them apart invites the half-applied state where
     * a company is back on list price still carrying an old discount.
     */
    public function saveBilling(): void
    {
        $this->ensureAdmin();

        $arrangement = BillingArrangement::tryFrom($this->arrangement);
        abort_if($arrangement === null, 422);

        $this->validate($this->billingRules($arrangement), $this->billingMessages());

        $plan = Plan::tryFrom($this->plan);
        abort_if($plan === null, 422);

        (new SetCompanyBillingAction)->execute($this->companyId, new CompanyBillingData(
            plan: $plan,
            billing_arrangement: $arrangement,
            custom_price_amount: $arrangement->needsPrice() ? MoneyInput::toMinorUnits($this->customPrice) : null,
            custom_price_currency: $arrangement->needsPrice() ? strtoupper($this->customCurrency) : null,
            custom_price_interval: $arrangement->needsPrice() ? BillingInterval::tryFrom($this->customInterval) : null,
            billing_notes: $this->billingNotes === '' ? null : $this->billingNotes,
            trial_ends_at: $this->trialEndsAtValue(),
        ));

        $company = (new CompanyRepository)->find($this->companyId);

        if ($company !== null) {
            $this->fillFrom($company);
        }

        $this->dispatch('toast', message: 'Billing updated.');
    }

    /**
     * Give (or extend) a trial by a number of days from today. Extending from
     * TODAY rather than from the existing end date is deliberate: "give them
     * another 30 days" said out loud means 30 days from now.
     */
    public function grantTrial(int $days): void
    {
        $this->ensureAdmin();
        abort_unless($days > 0 && $days <= 730, 422);

        $this->trialEndsAt = CarbonImmutable::now()->addDays($days)->format('Y-m-d');
        $this->saveBilling();
    }

    public function endTrial(): void
    {
        $this->ensureAdmin();

        $this->trialEndsAt = '';
        $this->saveBilling();
    }

    /**
     * @return array<string, mixed>
     */
    private function billingRules(BillingArrangement $arrangement): array
    {
        return [
            'plan' => ['required', Rule::in(array_column(Plan::cases(), 'value'))],
            'arrangement' => ['required', Rule::in(array_column(BillingArrangement::cases(), 'value'))],
            'customPrice' => $arrangement->needsPrice()
                ? ['required', 'regex:/^\d{1,7}([.,]\d{1,2})?$/']
                : ['nullable'],
            'customCurrency' => $arrangement->needsPrice()
                ? ['required', Rule::in(array_keys(Currency::SYMBOLS))]
                : ['nullable'],
            'customInterval' => $arrangement->needsPrice()
                ? ['required', Rule::in(array_column(BillingInterval::cases(), 'value'))]
                : ['nullable'],
            'billingNotes' => ['nullable', 'string', 'max:2000'],
            'trialEndsAt' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function billingMessages(): array
    {
        return [
            'customPrice.required' => 'Give the agreed amount, or choose a different arrangement.',
            'customPrice.regex' => 'Write the amount in pounds and pence, like 49.50.',
        ];
    }

    private function trialEndsAtValue(): ?CarbonImmutable
    {
        if ($this->trialEndsAt === '') {
            return null;
        }

        // End of the chosen day, so a trial "until the 30th" includes the 30th.
        return CarbonImmutable::parse($this->trialEndsAt)->endOfDay();
    }

    public function addUser(): void
    {
        $this->ensureAdmin();
        $this->validate([
            'newUserName' => 'required|string|max:255',
            'newUserEmail' => 'required|email|max:255|unique:users,email',
            'newUserRole' => 'required|string',
        ]);

        $role = Role::from($this->newUserRole);
        $company = (new CompanyRepository)->find($this->companyId);

        $user = (new CreateCompanyUserAction)->execute($this->companyId, $this->newUserName, $this->newUserEmail, $role);
        (new SendUserInviteAction)->execute($user->id, $company?->name ?? 'CellarOS');

        $this->reset(['newUserName', 'newUserEmail']);
        $this->newUserRole = Role::Member->value;
        $this->dispatch('toast', message: 'User added and invite sent.');
    }

    public function resendInvite(int $userId): void
    {
        $this->ensureAdmin();
        $user = $this->guardCompanyUser($userId);

        $company = (new CompanyRepository)->find($this->companyId);
        (new SendUserInviteAction)->execute($user->id, $company?->name ?? 'CellarOS');
        $this->dispatch('toast', message: 'Invite re-sent.');
    }

    public function removeUser(int $userId): void
    {
        $this->ensureAdmin();
        $user = $this->guardCompanyUser($userId);

        // Don't strip a company of its last owner (use Delete company instead).
        if ($user->role === Role::Owner && $this->ownerCount() <= 1) {
            $this->dispatch('toast', message: 'A company must keep at least one owner. Delete the company instead.');

            return;
        }

        (new DeleteUserAction)->execute($userId);
        $this->dispatch('toast', message: 'User removed.');
    }

    private function ownerCount(): int
    {
        return (new UserRepository)->forCompany($this->companyId)
            ->filter(fn ($u) => $u->role === Role::Owner)
            ->count();
    }

    public function deleteCompany()
    {
        $this->ensureAdmin();

        // Cancels any Stripe subscription, then cascades users/venues/orders.
        (new DeleteCompanyAction)->execute($this->companyId);

        session()->flash('success', 'Company deleted.');

        return $this->redirectRoute('admin.companies', navigate: true);
    }

    private function guardCompanyUser(int $userId): UserData
    {
        $user = (new UserRepository)->find($userId);
        abort_unless($user !== null && $user->company_id === $this->companyId, 403);

        return $user;
    }

    private function ensureAdmin(): void
    {
        abort_unless(Auth::guard('admin')->check(), 403);
    }

    public function render()
    {
        return view('livewire.admin.company-show', [
            'company' => (new CompanyRepository)->find($this->companyId),
            'users' => (new UserRepository)->forCompany($this->companyId),
            'venues' => (new VenueRepository)->getForCompany($this->companyId),
            'plans' => Plan::cases(),
            'roles' => Role::options(),
            'arrangements' => BillingArrangement::options(),
            'intervals' => BillingInterval::options(),
            'currencies' => collect(array_keys(Currency::SYMBOLS))->mapWithKeys(fn (string $c) => [$c => $c])->all(),
        ]);
    }
}
