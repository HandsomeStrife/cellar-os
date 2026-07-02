<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="relative w-full max-w-xs">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-muted-foreground">
                <x-icon.search class="size-4" />
            </span>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search suppliers…" class="block w-full rounded-md border border-input bg-card py-2 pl-9 pr-3 text-sm text-foreground shadow-sm transition placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring/40" />
        </div>
        <x-button wire:click="create"><x-icon.plus class="size-4" /> New supplier</x-button>
    </div>

    @if($showForm)
        <x-card title="New supplier">
            <form wire:submit="save" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input.text name="name" label="Company name" wire:model="name" required />
                    <x-input.text name="contact" label="Contact name" wire:model="contact" />
                    <x-input.email name="email" label="Email" wire:model="email" />
                    <x-input.text name="phone" label="Phone" wire:model="phone" />
                    <x-input.text name="website" label="Website" wire:model="website" />
                    <x-input.text name="location" label="Location" wire:model="location" />
                </div>
                <div class="flex gap-2">
                    <x-button type="submit">Create supplier</x-button>
                    <x-button type="button" variant="outline" wire:click="$set('showForm', false)">Cancel</x-button>
                </div>
            </form>
        </x-card>
    @endif

    @if($suppliers->total() === 0)
        <x-card><x-empty-state icon="building-2" title="No suppliers found" message="Create a supplier company to get started." /></x-card>
    @else
        <div class="overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
            <table class="w-full text-sm">
                <thead class="border-b border-border bg-secondary/40">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Company</th>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Contact</th>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Location</th>
                        {{-- Tier (Listed/Onboarded/Private) is what admins actually manage here;
                             "Status: Active" on every row said nothing. --}}
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Tier</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($suppliers as $supplier)
                        <tr wire:key="supplier-{{ $supplier->id }}" class="hover:bg-accent/40">
                            <td class="px-3 py-2.5 font-medium">{{ $supplier->name }}</td>
                            {{-- Research notes can end up in the contact field — clamp so one
                                 verbose cell doesn't triple the row height. --}}
                            <td class="max-w-xs px-3 py-2.5 text-muted-foreground">
                                <span class="line-clamp-1" title="{{ $supplier->contact }}">{{ $supplier->contact ?: '—' }}</span>
                            </td>
                            <td class="px-3 py-2.5 text-muted-foreground">{{ $supplier->location ?: '—' }}</td>
                            <td class="px-3 py-2.5"><x-badge :color="$supplier->tier?->getColour() ?? 'gray'">{{ $supplier->tier?->getLabel() ?? '—' }}</x-badge></td>
                            <td class="px-3 py-2.5 text-right">
                                <x-button :href="route('admin.suppliers.show', $supplier->uuid)" wire:navigate variant="outline" size="sm">Manage</x-button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div>{{ $suppliers->links() }}</div>
    @endif
</div>
