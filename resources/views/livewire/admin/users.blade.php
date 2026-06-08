<div class="space-y-6">
    <div class="relative w-full max-w-xs">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-muted-foreground">
            <x-icon.search class="size-4" />
        </span>
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search users…" class="block w-full rounded-md border border-input bg-card py-2 pl-9 pr-3 text-sm text-foreground shadow-sm transition placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/40" />
    </div>

    @if($users->total() === 0)
        <x-card><p class="py-8 text-center text-sm text-muted-foreground">No users found.</p></x-card>
    @else
        <div class="overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
            <table class="w-full text-sm">
                <thead class="border-b border-border bg-secondary/40">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Name</th>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Email</th>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Plan</th>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Joined</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($users as $user)
                        <tr wire:key="user-{{ $user->id }}" class="hover:bg-accent/40">
                            <td class="px-3 py-2.5 font-medium">{{ $user->full_name ?? '–' }}</td>
                            <td class="px-3 py-2.5 text-muted-foreground">{{ $user->email }}</td>
                            <td class="px-3 py-2.5">
                                <select wire:change="setPlan({{ $user->id }}, $event.target.value)" class="rounded-md border border-input bg-card px-2 py-1 text-xs shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/40">
                                    @foreach($plans as $plan)
                                        <option value="{{ $plan->value }}" @selected($plan === $user->plan)>{{ $plan->getLabel() }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-3 py-2.5 text-muted-foreground">{{ $user->created_at?->format('j M Y') }}</td>
                            <td class="px-3 py-2.5 text-right">
                                <x-button wire:click="deleteUser({{ $user->id }})" wire:confirm="Delete {{ $user->email }}? This cannot be undone." variant="ghost" size="sm" class="text-destructive hover:bg-destructive/10">
                                    <x-icon.trash-2 class="size-4" />
                                </x-button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div>{{ $users->links() }}</div>
    @endif
</div>
