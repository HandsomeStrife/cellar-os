<div class="space-y-6">
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
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($mine as $supplier)
                    @php($owned = $supplier->created_by_company_id === $currentCompanyId)
                    <div wire:key="mine-{{ $supplier->id }}" class="flex flex-col rounded-lg border border-border bg-card p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="truncate font-serif text-lg font-semibold">{{ $supplier->name }}</h3>
                                @if($supplier->location)
                                    <p class="mt-0.5 flex items-center gap-1 truncate text-sm text-muted-foreground"><x-icon.map-pin class="size-3.5 shrink-0" /> {{ $supplier->location }}</p>
                                @endif
                            </div>
                            <x-badge :color="$supplier->tier->getColour()">{{ $supplier->tier->getLabel() }}</x-badge>
                        </div>

                        <dl class="mt-4 flex-1 space-y-1.5 text-sm">
                            @if($supplier->contact)<div class="flex items-center gap-2 text-muted-foreground"><x-icon.user class="size-4 shrink-0" /><span class="truncate text-foreground">{{ $supplier->contact }}</span></div>@endif
                            @if($supplier->email)<div class="flex items-center gap-2 text-muted-foreground"><x-icon.mail class="size-4 shrink-0" /><span class="truncate text-foreground">{{ $supplier->email }}</span></div>@endif
                            <div class="flex items-center gap-2 text-muted-foreground">
                                <x-icon.map-pin class="size-4 shrink-0" />
                                <span class="truncate">{{ ($allocations[$supplier->id] ?? []) === [] ? 'No venues allocated' : count($allocations[$supplier->id]).' '.\Illuminate\Support\Str::plural('venue', count($allocations[$supplier->id])) }}</span>
                            </div>
                        </dl>

                        <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-border pt-4">
                            <x-button wire:click="startAllocate({{ $supplier->id }})" variant="outline" size="sm"><x-icon.map-pin class="size-4" /> Venues</x-button>
                            <x-button :href="route('suppliers.documents', $supplier->uuid)" wire:navigate variant="outline" size="sm"><x-icon.file-text class="size-4" /> Documents</x-button>
                            @if($owned)
                                <x-button wire:click="edit({{ $supplier->id }})" variant="ghost" size="sm" aria-label="Edit"><x-icon.pencil class="size-4" /></x-button>
                                <x-button wire:click="delete({{ $supplier->id }})" wire:confirm="Delete {{ $supplier->name }}? This cannot be undone." variant="ghost" size="sm" class="text-destructive hover:bg-destructive/10" aria-label="Delete"><x-icon.trash-2 class="size-4" /></x-button>
                            @else
                                <x-button wire:click="disconnect({{ $supplier->id }})" wire:confirm="Remove {{ $supplier->name }} from your suppliers?" variant="ghost" size="sm" class="text-destructive hover:bg-destructive/10">Disconnect</x-button>
                            @endif
                        </div>
                    </div>
                @endforeach
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
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($discover as $supplier)
                    <div wire:key="disc-{{ $supplier->id }}" class="flex flex-col rounded-lg border border-border bg-card p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="truncate font-serif text-lg font-semibold">{{ $supplier->name }}</h3>
                                @if($supplier->location)<p class="mt-0.5 truncate text-sm text-muted-foreground">{{ $supplier->location }}</p>@endif
                            </div>
                            <x-badge :color="$supplier->tier->getColour()">{{ $supplier->tier->getLabel() }}</x-badge>
                        </div>
                        <div class="mt-4 border-t border-border pt-4">
                            <x-button wire:click="connect({{ $supplier->id }})" size="sm"><x-icon.plus class="size-4" /> Connect</x-button>
                        </div>
                    </div>
                @endforeach
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
