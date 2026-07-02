<div class="space-y-6">
    <x-page-header title="Suppliers" subtitle="The merchants you buy from, and others you can connect to." />

    {{-- Tabs --}}
    <div class="flex items-center gap-1 border-b border-border">
        <button type="button" wire:click="$set('tab', 'mine')" @class([
            'border-b-2 px-4 py-2 text-sm font-medium transition',
            'border-primary text-foreground' => $tab === 'mine',
            'border-transparent text-muted-foreground hover:text-foreground' => $tab !== 'mine',
        ])>My suppliers</button>
        <button type="button" wire:click="$set('tab', 'discover')" @class([
            'border-b-2 px-4 py-2 text-sm font-medium transition',
            'border-primary text-foreground' => $tab === 'discover',
            'border-transparent text-muted-foreground hover:text-foreground' => $tab !== 'discover',
        ])>Discover</button>
    </div>

    @if($tab === 'mine')
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="relative w-full max-w-xs">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-muted-foreground"><x-icon.search class="size-4" /></span>
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search your suppliers…" class="block w-full rounded-md border border-input bg-card py-2 pl-9 pr-3 text-sm text-foreground shadow-sm transition placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/40" />
            </div>
            <x-button wire:click="create"><x-icon.plus class="size-4" /> Add a supplier</x-button>
        </div>

        @if($mine->isEmpty())
            <x-card><x-empty-state icon="users" title="No suppliers yet" message="Connect to a listed supplier under Discover, or add your own." /></x-card>
        @else
            {{-- A table, like every other list in the app — suppliers scan as
                 rows, not as a grid of uneven cards. --}}
            <div class="overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead class="border-b border-border bg-secondary/40">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Supplier</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Tier</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Contact</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Venues</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($mine as $supplier)
                            @php($owned = $supplier->created_by_company_id === $currentCompanyId)
                            @php($venueCount = count($allocations[$supplier->id] ?? []))
                            <tr wire:key="mine-{{ $supplier->id }}" class="hover:bg-accent/40">
                                <td class="max-w-xs px-3 py-2.5">
                                    <div class="truncate font-medium text-foreground">{{ $supplier->name }}</div>
                                    @if($supplier->location)
                                        <div class="truncate text-xs text-muted-foreground">{{ $supplier->location }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5"><x-badge :color="$supplier->tier->getColour()">{{ $supplier->tier->getLabel() }}</x-badge></td>
                                <td class="max-w-xs px-3 py-2.5">
                                    @if($supplier->contact || $supplier->email)
                                        @if($supplier->contact)<div class="truncate text-foreground">{{ $supplier->contact }}</div>@endif
                                        @if($supplier->email)<div class="truncate text-xs text-muted-foreground">{{ $supplier->email }}</div>@endif
                                    @else
                                        <span class="text-muted-foreground">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-muted-foreground">
                                    {{ $venueCount === 0 ? 'None' : $venueCount.' '.\Illuminate\Support\Str::plural('venue', $venueCount) }}
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <x-button wire:click="startAllocate({{ $supplier->id }})" variant="ghost" size="sm"><x-icon.map-pin class="size-4" /> Venues</x-button>
                                        <x-button :href="route('suppliers.documents', $supplier->uuid)" wire:navigate variant="ghost" size="sm"><x-icon.file-text class="size-4" /> Documents</x-button>
                                        @if($owned)
                                            <x-button wire:click="edit({{ $supplier->id }})" variant="ghost" size="sm" title="Edit supplier"><x-icon.pencil class="size-4" /></x-button>
                                            <x-button wire:click="delete({{ $supplier->id }})" wire:confirm="Delete {{ $supplier->name }}? This cannot be undone." variant="ghost" size="sm" class="text-destructive hover:bg-destructive/10" title="Delete supplier"><x-icon.trash-2 class="size-4" /></x-button>
                                        @else
                                            <x-button wire:click="disconnect({{ $supplier->id }})" wire:confirm="Remove {{ $supplier->name }} from your suppliers?" variant="ghost" size="sm" class="text-destructive hover:bg-destructive/10" title="Remove from my suppliers"><x-icon.x class="size-4" /> Disconnect</x-button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @else
        {{-- Discover --}}
        <div class="relative w-full max-w-xs">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-muted-foreground"><x-icon.search class="size-4" /></span>
            <input type="search" wire:model.live.debounce.300ms="discoverSearch" placeholder="Search listed suppliers…" class="block w-full rounded-md border border-input bg-card py-2 pl-9 pr-3 text-sm text-foreground shadow-sm transition placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/40" />
        </div>

        @if($discover->isEmpty())
            <x-card><x-empty-state icon="users" title="Nothing to discover" message="You're connected to every listed supplier." /></x-card>
        @else
            <div class="overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead class="border-b border-border bg-secondary/40">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Supplier</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Location</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Tier</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($discover as $supplier)
                            <tr wire:key="disc-{{ $supplier->id }}" class="hover:bg-accent/40">
                                <td class="max-w-xs px-3 py-2.5">
                                    <span class="block truncate font-medium text-foreground">{{ $supplier->name }}</span>
                                </td>
                                <td class="max-w-xs px-3 py-2.5 text-muted-foreground">
                                    <span class="block truncate">{{ $supplier->location ?: '—' }}</span>
                                </td>
                                <td class="px-3 py-2.5"><x-badge :color="$supplier->tier->getColour()">{{ $supplier->tier->getLabel() }}</x-badge></td>
                                <td class="px-3 py-2.5 text-right">
                                    <x-button wire:click="connect({{ $supplier->id }})" variant="outline" size="sm"><x-icon.plus class="size-4" /> Connect</x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif

    {{-- Add / edit private supplier --}}
    <x-modal model="showForm" :title="$editingId ? 'Edit supplier' : 'Add a supplier'">
        <form wire:submit="save" class="space-y-4">
            <x-input.text name="name" label="Name" wire:model="name" required autofocus />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input.text name="contact" label="Contact name" wire:model="contact" />
                <x-input.text name="phone" label="Phone" wire:model="phone" />
            </div>
            <x-input.email name="email" label="Email" wire:model="email" />
            <x-input.text name="location" label="Location" wire:model="location" hint="e.g. Bordeaux, France" />
            <div class="flex items-center justify-end gap-2 pt-2">
                <x-button type="button" variant="outline" wire:click="$set('showForm', false)">Cancel</x-button>
                <x-button type="submit">{{ $editingId ? 'Save changes' : 'Add supplier' }}</x-button>
            </div>
        </form>
    </x-modal>

    {{-- Allocate to venues --}}
    <x-modal model="showAllocate" title="Allocate to venues">
        @if($venues->isEmpty())
            <p class="text-sm text-muted-foreground">You have no venues yet.</p>
        @else
            <div class="space-y-2">
                @foreach($venues as $venue)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="allocVenueIds" value="{{ $venue->id }}" class="accent-primary" />
                        {{ $venue->name }}
                    </label>
                @endforeach
            </div>
            <div class="flex items-center justify-end gap-2 pt-4">
                <x-button type="button" variant="outline" wire:click="$set('showAllocate', false)">Cancel</x-button>
                <x-button wire:click="saveAllocation">Save</x-button>
            </div>
        @endif
    </x-modal>
</div>
