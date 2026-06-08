<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use Domain\Billing\Enums\Plan;
use Domain\Company\Actions\DeleteCompanyAction;
use Domain\Company\Actions\SetCompanyPlanAction;
use Domain\Company\Repositories\CompanyRepository;
use Domain\User\Actions\CreateCompanyUserAction;
use Domain\User\Actions\DeleteUserAction;
use Domain\User\Actions\SendUserInviteAction;
use Domain\User\Data\UserData;
use Domain\User\Enums\Role;
use Domain\User\Repositories\UserRepository;
use Domain\Venue\Repositories\VenueRepository;
use Illuminate\Support\Facades\Auth;
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

    public string $plan = Plan::Free->value;

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
        $this->plan = $company->plan->value;
    }

    public function setPlan(): void
    {
        $this->ensureAdmin();

        $plan = Plan::tryFrom($this->plan);
        abort_if($plan === null, 422);

        (new SetCompanyPlanAction)->execute($this->companyId, $plan);
        $this->dispatch('toast', message: 'Plan updated.');
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
        ]);
    }
}
