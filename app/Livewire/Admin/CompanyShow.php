<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Concerns\EditsCompanyBilling;
use Carbon\CarbonImmutable;
use Domain\Billing\Enums\BillingArrangement;
use Domain\Billing\Enums\BillingInterval;
use Domain\Billing\Enums\Plan;
use Domain\Company\Actions\DeleteCompanyAction;
use Domain\Company\Actions\SetCompanyBillingAction;
use Domain\Company\Data\CompanyData;
use Domain\Company\Repositories\CompanyRepository;
use Domain\User\Actions\CreateCompanyUserAction;
use Domain\User\Actions\DeleteUserAction;
use Domain\User\Actions\SendUserInviteAction;
use Domain\User\Data\UserData;
use Domain\User\Enums\Role;
use Domain\User\Repositories\UserRepository;
use Domain\Venue\Actions\SyncUserVenuesAction;
use Domain\Venue\Repositories\VenueRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Company')]
class CompanyShow extends Component
{
    use EditsCompanyBilling;

    /** Locked: these choose which company gets written to. */
    #[Locked]
    public string $uuid = '';

    #[Locked]
    public ?int $companyId = null;

    public string $plan = Plan::Pro->value;

    /** Blank means no trial. Held as a date so it survives a page refresh. */
    public string $trialEndsAt = '';

    /**
     * What the trial date was when the form loaded.
     *
     * A lapsed company ALREADY carries a date in the past, so refusing every
     * past date would make its billing form unsaveable — and the only way
     * through would be to blank the field, which reads as "never had a trial"
     * and hands the lapsed plan back for free. The past-date rule therefore
     * applies only to a date someone has actually changed.
     */
    #[Locked]
    public string $storedTrialEndsAt = '';

    /**
     * The stored date to the SECOND. The form field only carries a day, so
     * re-parsing an untouched value would round it back up to end-of-day —
     * quietly reviving a trial that "End it now" cut short an hour ago.
     */
    #[Locked]
    public ?string $storedTrialEndsAtExact = null;

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
        $this->storedTrialEndsAt = $this->trialEndsAt;
        $this->storedTrialEndsAtExact = $company->trial_ends_at?->toIso8601String();
    }

    /**
     * Plan, arrangement, price and trial are saved together: they constrain
     * one another, and saving them apart invites the half-applied state where
     * a company is back on list price still carrying an old discount.
     */
    public function saveBilling(): void
    {
        $this->ensureAdmin();

        $arrangement = $this->currentArrangement();

        $this->validate(
            [
                ...$this->billingRules($arrangement),
                // A NEW date in the past would be a silent entitlement
                // removal: mistype the year and a Group company drops to Pro
                // with no confirmation. Cutting one short is what "End it now"
                // is for. An UNCHANGED past date has to save, though — every
                // lapsed company carries one, and blocking it would make their
                // billing form unusable.
                'trialEndsAt' => $this->trialEndsAt === $this->storedTrialEndsAt
                    ? ['nullable', 'date_format:Y-m-d']
                    : ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            ],
            [
                ...$this->billingMessages(),
                'trialEndsAt.after_or_equal' => 'A trial can\'t be set to end in the past. Use "End it now" to cut one short.',
            ],
        );

        $this->persistBilling($arrangement, $this->trialEndsAtValue());
    }

    /**
     * Write the terms. Separate from {@see self::saveBilling()} so the trial
     * buttons can set a date the form itself would refuse — "end it now" means
     * a date in the past, which is exactly what the form guards against.
     */
    private function persistBilling(BillingArrangement $arrangement, ?CarbonImmutable $trialEndsAt): void
    {
        $plan = Plan::tryFrom($this->plan);
        abort_if($plan === null, 422);

        (new SetCompanyBillingAction)->execute(
            $this->companyId,
            $this->billingTerms($plan, $arrangement, $trialEndsAt),
        );

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
     *
     * Validation runs before the date is touched, so a form that fails to save
     * never leaves a trial date on screen that was never stored.
     */
    public function grantTrial(int $days): void
    {
        $this->ensureAdmin();
        abort_unless($days > 0 && $days <= 730, 422);

        $arrangement = $this->currentArrangement();
        $this->validate($this->billingRules($arrangement), $this->billingMessages());

        $this->persistBilling($arrangement, CarbonImmutable::now()->addDays($days)->endOfDay());
    }

    /**
     * Forget the trial ever happened, keeping the plan. For correcting a
     * mistake — NOT for cutting a trial short, which is {@see self::endTrialNow()}.
     */
    public function removeTrial(): void
    {
        $this->ensureAdmin();

        $arrangement = $this->currentArrangement();
        $this->validate($this->billingRules($arrangement), $this->billingMessages());

        $this->persistBilling($arrangement, null);
    }

    /**
     * Expire the trial now, which is what "end the trial" means out loud: the
     * company drops to whatever it is entitled to without one.
     */
    public function endTrialNow(): void
    {
        $this->ensureAdmin();

        $arrangement = $this->currentArrangement();
        $this->validate($this->billingRules($arrangement), $this->billingMessages());

        $this->persistBilling($arrangement, CarbonImmutable::now()->subSecond());
    }

    private function trialEndsAtValue(): ?CarbonImmutable
    {
        if ($this->trialEndsAt === '') {
            return null;
        }

        // Untouched? Keep what's stored, to the second. Re-deriving it from
        // the day would push an already-expired trial back to end-of-day and
        // bring it back to life for the rest of today.
        if ($this->trialEndsAt === $this->storedTrialEndsAt && $this->storedTrialEndsAtExact !== null) {
            return CarbonImmutable::parse($this->storedTrialEndsAtExact);
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
            'newUserRole' => ['required', Rule::in(array_column(Role::cases(), 'value'))],
        ]);

        $role = Role::from($this->newUserRole);
        $company = (new CompanyRepository)->find($this->companyId);

        $user = (new CreateCompanyUserAction)->execute($this->companyId, $this->newUserName, $this->newUserEmail, $role);

        // A member with no venue can see nothing at all, so give every new
        // seat access to the company's venues. Owners and managers see them
        // all by role anyway; this is what makes a MEMBER usable.
        (new SyncUserVenuesAction)->execute(
            $user->id,
            (new VenueRepository)->getForCompany($this->companyId)->pluck('id')->all(),
        );

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
            'roles' => Role::options(),
            ...$this->billingOptions(),
        ]);
    }
}
