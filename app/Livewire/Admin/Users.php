<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use Domain\Billing\Enums\Plan;
use Domain\User\Actions\DeleteUserAction;
use Domain\User\Actions\SetUserPlanAction;
use Domain\User\Repositories\UserRepository;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Users')]
class Users extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function setPlan(int $id, string $plan): void
    {
        $this->ensureAdmin();

        $planEnum = Plan::tryFrom($plan);
        abort_if($planEnum === null, 422);

        (new SetUserPlanAction)->execute($id, $planEnum);
        $this->dispatch('toast', message: 'Plan updated.');
    }

    public function deleteUser(int $id): void
    {
        $this->ensureAdmin();

        (new DeleteUserAction)->execute($id);
        $this->dispatch('toast', message: 'User deleted.');
    }

    /**
     * Defence-in-depth: destructive admin actions verify the admin guard
     * intrinsically, not only via route middleware.
     */
    private function ensureAdmin(): void
    {
        abort_unless(Auth::guard('admin')->check(), 403);
    }

    public function render()
    {
        return view('livewire.admin.users', [
            'users' => (new UserRepository)->paginate($this->search),
            'plans' => Plan::cases(),
        ]);
    }
}
