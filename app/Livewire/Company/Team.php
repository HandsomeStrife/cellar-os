<?php

declare(strict_types=1);

namespace App\Livewire\Company;

use App\Livewire\Concerns\WithTenant;
use Domain\User\Actions\CreateCompanyUserAction;
use Domain\User\Actions\DeleteUserAction;
use Domain\User\Actions\SendUserInviteAction;
use Domain\User\Actions\UpdateCompanyUserAction;
use Domain\User\Data\UserData;
use Domain\User\Enums\Role;
use Domain\User\Repositories\UserRepository;
use Domain\Venue\Actions\SyncUserVenuesAction;
use Domain\Venue\Repositories\VenueRepository;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Team')]
class Team extends Component
{
    use WithTenant;

    public bool $showInvite = false;

    public string $name = '';

    public string $email = '';

    public string $role = Role::Member->value;

    /** @var array<int, int> */
    public array $venueIds = [];

    // Inline edit
    public ?int $editingId = null;

    public string $editName = '';

    public string $editRole = Role::Member->value;

    /** @var array<int, int> */
    public array $editVenueIds = [];

    public function mount(): void
    {
        abort_unless($this->currentUser()?->role->canManageTeam() ?? false, 403);
    }

    public function invite(): void
    {
        $actor = $this->requireManager();

        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'role' => 'required|string',
            'venueIds' => 'array',
            'venueIds.*' => 'integer',
        ]);

        $role = Role::from($this->role);
        abort_unless($actor->role->canAssignRole($role), 403);

        $user = (new CreateCompanyUserAction)->execute($actor->company_id, $this->name, $this->email, $role);
        (new SyncUserVenuesAction)->execute($user->id, $this->venueAssignment($role));
        (new SendUserInviteAction)->execute($user->id, $this->currentCompany()?->name ?? 'CellarOS');

        $this->reset(['showInvite', 'name', 'email', 'role', 'venueIds']);
        $this->role = Role::Member->value;
        $this->dispatch('toast', message: 'Invite sent.');
    }

    public function startEdit(int $id): void
    {
        $actor = $this->requireManager();
        $member = $this->guardSameCompany($id);
        abort_unless($actor->role->canAssignRole($member->role), 403);

        $this->editingId = $member->id;
        $this->editName = $member->full_name ?? '';
        $this->editRole = $member->role->value;
        $this->editVenueIds = (new VenueRepository)->getAssignedToUser($member->id)->pluck('id')->all();
    }

    public function saveEdit(): void
    {
        $actor = $this->requireManager();
        abort_if($this->editingId === null, 422);
        $member = $this->guardSameCompany($this->editingId);

        $this->validate([
            'editName' => 'required|string|max:255',
            'editRole' => 'required|string',
            'editVenueIds' => 'array',
            'editVenueIds.*' => 'integer',
        ]);

        $newRole = Role::from($this->editRole);
        // Actor must outrank (or equal) both the current and the target role.
        abort_unless($actor->role->canAssignRole($member->role) && $actor->role->canAssignRole($newRole), 403);

        // Never leave the company without an owner (who alone can manage billing).
        if ($member->role === Role::Owner && $newRole !== Role::Owner && $this->ownerCount() <= 1) {
            $this->dispatch('toast', message: 'A company must keep at least one owner.');

            return;
        }

        (new UpdateCompanyUserAction)->execute($member->id, $this->editName, $newRole);
        (new SyncUserVenuesAction)->execute($member->id, $this->venueAssignment($newRole));

        $this->editingId = null;
        $this->dispatch('toast', message: 'Member updated.');
    }

    public function resendInvite(int $id): void
    {
        $this->requireManager();
        $member = $this->guardSameCompany($id);

        (new SendUserInviteAction)->execute($member->id, $this->currentCompany()?->name ?? 'CellarOS');
        $this->dispatch('toast', message: 'Invite re-sent.');
    }

    public function remove(int $id): void
    {
        $actor = $this->requireManager();
        $member = $this->guardSameCompany($id);

        // Can't remove yourself, and only equal/lower-ranked seats.
        abort_if($member->id === $actor->id, 422);
        abort_unless($actor->role->canAssignRole($member->role), 403);

        // Never remove the last owner.
        if ($member->role === Role::Owner && $this->ownerCount() <= 1) {
            $this->dispatch('toast', message: 'A company must keep at least one owner.');

            return;
        }

        (new DeleteUserAction)->execute($member->id);
        $this->dispatch('toast', message: 'Member removed.');
    }

    private function ownerCount(): int
    {
        return (new UserRepository)->forCompany($this->currentUser()?->company_id ?? 0)
            ->filter(fn (UserData $u) => $u->role === Role::Owner)
            ->count();
    }

    private function requireManager(): UserData
    {
        $actor = $this->currentUser();
        abort_unless($actor !== null && $actor->company_id !== null && $actor->role->canManageTeam(), 403);

        return $actor;
    }

    private function guardSameCompany(int $userId): UserData
    {
        $member = (new UserRepository)->find($userId);
        abort_unless($member !== null && $member->company_id === $this->currentUser()?->company_id, 403);

        return $member;
    }

    /**
     * Owners/managers implicitly see every venue, so we don't store pivot rows
     * for them; members get exactly the venues chosen.
     *
     * @return array<int, int>
     */
    private function venueAssignment(Role $role): array
    {
        if ($role->seesAllVenues()) {
            return [];
        }

        $companyVenueIds = $this->accessibleVenues()->pluck('id')->all();

        return array_values(array_intersect(
            $this->editingId !== null ? $this->editVenueIds : $this->venueIds,
            $companyVenueIds,
        ));
    }

    public function render()
    {
        $actor = $this->currentUser();
        $assignableRoles = collect(Role::cases())
            ->filter(fn (Role $r) => $actor?->role->canAssignRole($r) ?? false)
            ->mapWithKeys(fn (Role $r) => [$r->value => $r->getLabel()])
            ->all();

        $members = (new UserRepository)->forCompany($actor?->company_id ?? 0);
        $venueRepo = new VenueRepository;

        // Per-member venue label: owners/managers see everything; members get their list.
        $memberVenues = $members->mapWithKeys(fn ($m) => [
            $m->id => $m->role->seesAllVenues()
                ? 'All venues'
                : ($venueRepo->getAssignedToUser($m->id)->pluck('name')->implode(', ') ?: '—'),
        ])->all();

        return view('livewire.company.team', [
            'members' => $members,
            'memberVenues' => $memberVenues,
            'venues' => $this->accessibleVenues(),
            'assignableRoles' => $assignableRoles,
            'currentUserId' => $actor?->id,
        ]);
    }
}
