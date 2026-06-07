<div class="space-y-6">
    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="relative w-full max-w-xs">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-muted-foreground">
                <x-icon.search class="size-4" />
            </span>
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Search suppliers…"
                class="block w-full rounded-md border border-input bg-card py-2 pl-9 pr-3 text-sm text-foreground shadow-sm transition placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/40"
            />
        </div>

        <x-button wire:click="create">
            <x-icon.plus class="size-4" />
            New supplier
        </x-button>
    </div>

    {{-- Card grid --}}
    @if($suppliers->isEmpty())
        <x-card>
            <div class="flex flex-col items-center justify-center gap-3 py-10 text-center">
                <span class="flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <x-icon.users class="size-6" />
                </span>
                <div>
                    <p class="font-medium text-foreground">{{ $search === '' ? 'No suppliers yet' : 'No matching suppliers' }}</p>
                    <p class="text-sm text-muted-foreground">{{ $search === '' ? 'Add the merchants and importers you buy from.' : 'Try a different search.' }}</p>
                </div>
                @if($search === '')
                    <x-button wire:click="create" variant="outline" size="sm">
                        <x-icon.plus class="size-4" />
                        New supplier
                    </x-button>
                @endif
            </div>
        </x-card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($suppliers as $supplier)
                <div wire:key="supplier-{{ $supplier->id }}" class="flex flex-col rounded-lg border border-border bg-card p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="truncate font-serif text-lg font-semibold">{{ $supplier->name }}</h3>
                            @if($supplier->location)
                                <p class="mt-0.5 flex items-center gap-1 truncate text-sm text-muted-foreground">
                                    <x-icon.map-pin class="size-3.5 shrink-0" />
                                    {{ $supplier->location }}
                                </p>
                            @endif
                        </div>
                        <button
                            type="button"
                            wire:click="toggleStatus({{ $supplier->id }})"
                            title="Toggle status"
                            class="shrink-0"
                        >
                            <x-badge :color="$supplier->status->getColour()">{{ $supplier->status->getLabel() }}</x-badge>
                        </button>
                    </div>

                    <dl class="mt-4 flex-1 space-y-1.5 text-sm">
                        @if($supplier->contact)
                            <div class="flex items-center gap-2 text-muted-foreground">
                                <x-icon.user class="size-4 shrink-0" />
                                <span class="truncate text-foreground">{{ $supplier->contact }}</span>
                            </div>
                        @endif
                        @if($supplier->email)
                            <div class="flex items-center gap-2 text-muted-foreground">
                                <x-icon.mail class="size-4 shrink-0" />
                                <a href="mailto:{{ $supplier->email }}" class="truncate text-primary hover:underline">{{ $supplier->email }}</a>
                            </div>
                        @endif
                        @if($supplier->phone)
                            <div class="flex items-center gap-2 text-muted-foreground">
                                <x-icon.phone class="size-4 shrink-0" />
                                <span class="truncate text-foreground">{{ $supplier->phone }}</span>
                            </div>
                        @endif
                    </dl>

                    <div class="mt-4 flex items-center gap-2 border-t border-border pt-4">
                        <x-button wire:click="edit({{ $supplier->id }})" variant="outline" size="sm">
                            <x-icon.pencil class="size-4" />
                            Edit
                        </x-button>
                        <x-button
                            wire:click="delete({{ $supplier->id }})"
                            wire:confirm="Delete {{ $supplier->name }}? This cannot be undone."
                            variant="ghost"
                            size="sm"
                            class="text-destructive hover:bg-destructive/10"
                        >
                            <x-icon.trash-2 class="size-4" />
                            Delete
                        </x-button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Create / edit modal --}}
    <x-modal model="showForm" :title="$editingId ? 'Edit supplier' : 'New supplier'">
        <form wire:submit="save" class="space-y-4">
            <x-input.text name="name" label="Name" wire:model="name" required autofocus />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-input.text name="contact" label="Contact name" wire:model="contact" />
                <x-input.text name="phone" label="Phone" wire:model="phone" />
            </div>

            <x-input.email name="email" label="Email" wire:model="email" />

            <x-input.text name="location" label="Location" wire:model="location" hint="e.g. Bordeaux, France" />

            <x-input.select name="status" label="Status" :options="$statuses" wire:model="status" />

            <div class="flex items-center justify-end gap-2 pt-2">
                <x-button type="button" variant="outline" wire:click="$set('showForm', false)">Cancel</x-button>
                <x-button type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">{{ $editingId ? 'Save changes' : 'Create supplier' }}</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </x-button>
            </div>
        </form>
    </x-modal>
</div>
