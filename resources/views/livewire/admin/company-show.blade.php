<div class="space-y-6">
    <div>
        <a href="{{ route('admin.companies') }}" wire:navigate class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
            <x-icon.chevron-right class="size-4 rotate-180" /> Back to companies
        </a>
        <h2 class="mt-2 font-serif text-2xl font-semibold">{{ $company?->name }}</h2>
    </div>

    {{-- Plan --}}
    <x-card title="Subscription plan">
        <form wire:submit="setPlan" class="flex flex-wrap items-end gap-3">
            <div class="w-48">
                <x-input.select name="plan" label="Plan" wire:model="plan" :options="collect($plans)->mapWithKeys(fn($p) => [$p->value => $p->getLabel()])->all()" />
            </div>
            <x-button type="submit">Save plan</x-button>
        </form>
    </x-card>

    {{-- Team --}}
    <x-card title="Team">
        @if($users->isEmpty())
            <p class="text-sm text-muted-foreground">No users yet.</p>
        @else
            <div class="overflow-x-auto rounded-lg border border-border">
                <table class="w-full text-sm">
                    <thead class="border-b border-border bg-secondary/40">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Name</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Email</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Role</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Access</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($users as $member)
                            <tr wire:key="cuser-{{ $member->id }}" class="hover:bg-accent/40">
                                <td class="px-3 py-2.5 font-medium">{{ $member->full_name ?: '—' }}</td>
                                <td class="px-3 py-2.5 text-muted-foreground">{{ $member->email }}</td>
                                <td class="px-3 py-2.5"><x-badge color="gray">{{ $member->role->getLabel() }}</x-badge></td>
                                <td class="px-3 py-2.5">
                                    @if($member->has_password)
                                        <x-badge color="green">Active</x-badge>
                                    @else
                                        <x-badge color="amber">Invite pending</x-badge>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <x-button wire:click="resendInvite({{ $member->id }})" variant="ghost" size="sm" aria-label="Resend invite"><x-icon.mail class="size-4" /></x-button>
                                        <x-button wire:click="removeUser({{ $member->id }})" wire:confirm="Remove {{ $member->email }}?" variant="ghost" size="sm" class="text-destructive hover:bg-destructive/10" aria-label="Remove"><x-icon.trash-2 class="size-4" /></x-button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <form wire:submit="addUser" class="mt-5 border-t border-border pt-5">
            <p class="mb-3 text-sm font-medium">Add a user</p>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-input.text name="newUserName" label="Name" wire:model="newUserName" required />
                <x-input.email name="newUserEmail" label="Email" wire:model="newUserEmail" required />
                <x-input.select name="newUserRole" label="Role" wire:model="newUserRole" :options="$roles" />
            </div>
            <div class="mt-4">
                <x-button type="submit"><x-icon.mail class="size-4" /> Add user &amp; send invite</x-button>
            </div>
        </form>
    </x-card>

    {{-- Danger zone --}}
    <x-card title="Danger zone">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-muted-foreground">Permanently delete this company, its users, venues, inventory and orders. Cancels any active subscription. This cannot be undone.</p>
            <x-button wire:click="deleteCompany" wire:confirm="Delete {{ $company?->name }} and ALL its data? This cannot be undone." variant="danger" size="sm">
                <x-icon.trash-2 class="size-4" /> Delete company
            </x-button>
        </div>
    </x-card>

    {{-- Venues --}}
    <x-card title="Venues">
        @if($venues->isEmpty())
            <p class="text-sm text-muted-foreground">No venues yet.</p>
        @else
            <ul class="divide-y divide-border">
                @foreach($venues as $venue)
                    <li class="flex items-center justify-between gap-3 py-2.5">
                        <span class="font-medium">{{ $venue->name }}</span>
                        <span class="text-sm text-muted-foreground">{{ collect([$venue->city, $venue->country])->filter()->implode(', ') ?: '—' }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</div>
