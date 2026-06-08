<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="font-serif text-2xl font-semibold">Team</h2>
            <p class="mt-1 text-sm text-muted-foreground">Invite colleagues and choose which venues each can access.</p>
        </div>
        <x-button wire:click="$set('showInvite', true)"><x-icon.plus class="size-4" /> Invite user</x-button>
    </div>

    @if($showInvite)
        <x-card title="Invite a user">
            <form wire:submit="invite" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input.text name="name" label="Name" wire:model="name" required />
                    <x-input.email name="email" label="Email" wire:model="email" required />
                    <x-input.select name="role" label="Role" :options="$assignableRoles" wire:model.live="role" />
                </div>

                <div x-data x-show="$wire.role === 'member'">
                    <x-input.label>Venues this member can access</x-input.label>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        @foreach($venues as $venue)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="venueIds" value="{{ $venue->id }}" class="accent-primary" />
                                {{ $venue->name }}
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">Owners and managers can access every venue automatically.</p>
                </div>

                <div class="flex gap-2">
                    <x-button type="submit"><x-icon.mail class="size-4" /> Send invite</x-button>
                    <x-button type="button" variant="outline" wire:click="$set('showInvite', false)">Cancel</x-button>
                </div>
            </form>
        </x-card>
    @endif

    <div class="overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
        <table class="w-full text-sm">
            <thead class="border-b border-border bg-secondary/40">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Name</th>
                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Role</th>
                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Venues</th>
                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Access</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @foreach($members as $member)
                    <tr wire:key="member-{{ $member->id }}" class="hover:bg-accent/40">
                        @if($editingId === $member->id)
                            <td class="px-3 py-2.5"><x-input.text name="editName" wire:model="editName" /></td>
                            <td class="px-3 py-2.5"><x-input.select name="editRole" :options="$assignableRoles" wire:model.live="editRole" /></td>
                            <td class="px-3 py-2.5" colspan="2">
                                <div x-data x-show="$wire.editRole === 'member'" class="grid gap-1">
                                    @foreach($venues as $venue)
                                        <label class="flex items-center gap-2 text-xs">
                                            <input type="checkbox" wire:model="editVenueIds" value="{{ $venue->id }}" class="accent-primary" />
                                            {{ $venue->name }}
                                        </label>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-3 py-2.5 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <x-button wire:click="saveEdit" size="sm">Save</x-button>
                                    <x-button wire:click="$set('editingId', null)" variant="outline" size="sm">Cancel</x-button>
                                </div>
                            </td>
                        @else
                            <td class="px-3 py-2.5">
                                <p class="font-medium">{{ $member->full_name ?: '—' }}</p>
                                <p class="text-xs text-muted-foreground">{{ $member->email }}</p>
                            </td>
                            <td class="px-3 py-2.5"><x-badge color="gray">{{ $member->role->getLabel() }}</x-badge></td>
                            <td class="px-3 py-2.5 text-muted-foreground">{{ $memberVenues[$member->id] ?? '—' }}</td>
                            <td class="px-3 py-2.5">
                                @if($member->has_password)
                                    <x-badge color="green">Active</x-badge>
                                @else
                                    <x-badge color="amber">Invite pending</x-badge>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    @unless($member->has_password)
                                        <x-button wire:click="resendInvite({{ $member->id }})" variant="ghost" size="sm" aria-label="Resend invite"><x-icon.mail class="size-4" /></x-button>
                                    @endunless
                                    <x-button wire:click="startEdit({{ $member->id }})" variant="ghost" size="sm" aria-label="Edit"><x-icon.pencil class="size-4" /></x-button>
                                    @if($member->id !== $currentUserId)
                                        <x-button wire:click="remove({{ $member->id }})" wire:confirm="Remove {{ $member->email }}?" variant="ghost" size="sm" class="text-destructive hover:bg-destructive/10" aria-label="Remove"><x-icon.trash-2 class="size-4" /></x-button>
                                    @endif
                                </div>
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
