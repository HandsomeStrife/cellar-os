@use('Domain\Shared\Support\Currency')

<div class="space-y-6">
    @if(! $canInventory)
        {{-- Whole feature gated (Starter+) --}}
        <x-upgrade-gate
            title="Inventory is a paid feature"
            message="Track received stock per venue, archive lines, and attach invoices and tasting notes."
            plan="Starter"
        />
    @elseif($venues->isEmpty())
        {{-- No venue yet --}}
        <x-card>
            <div class="flex flex-col items-center justify-center gap-3 py-10 text-center">
                <span class="flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <x-icon.building-2 class="size-6" />
                </span>
                <div>
                    <p class="font-medium text-foreground">Create your first venue</p>
                    <p class="text-sm text-muted-foreground">Inventory is tracked per venue (a restaurant, bar or store).</p>
                </div>
                <x-button wire:click="$set('showVenueForm', true)">
                    <x-icon.plus class="size-4" />
                    New venue
                </x-button>
            </div>
        </x-card>
    @else
        {{-- Toolbar --}}
        <div class="flex flex-wrap items-center gap-3">
            <select
                wire:change="selectVenue($event.target.value)"
                class="rounded-md border border-input bg-card px-3 py-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/40"
            >
                @foreach($venues as $venue)
                    <option value="{{ $venue->id }}" @selected($venue->id === $venueId)>{{ $venue->name }}</option>
                @endforeach
            </select>

            @if($canMultiVenue)
                <x-button wire:click="$set('showVenueForm', true)" variant="ghost" size="sm" title="New venue (Group plan)">
                    <x-icon.plus class="size-4" />
                </x-button>
            @endif

            <div class="relative w-full max-w-xs">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-muted-foreground">
                    <x-icon.search class="size-4" />
                </span>
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search wines in stock…"
                    class="block w-full rounded-md border border-input bg-card py-2 pl-9 pr-3 text-sm text-foreground shadow-sm transition placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/40"
                />
            </div>

            <div class="ml-auto flex items-center gap-2">
                <x-button wire:click="$toggle('showArchived')" variant="{{ $showArchived ? 'secondary' : 'outline' }}" size="sm">
                    <x-icon.archive class="size-4" />
                    {{ $showArchived ? 'Viewing archived' : 'Active' }}
                </x-button>

                @if($canManualAdd)
                    <x-button wire:click="$set('showAddForm', true)">
                        <x-icon.plus class="size-4" />
                        Receive stock
                    </x-button>
                @endif
            </div>
        </div>

        @if(! $canManualAdd)
            <x-alert variant="info">
                Manually adding stock is a <span class="font-medium">Pro</span> feature. Receiving against a purchase order will populate inventory automatically.
            </x-alert>
        @endif

        {{-- Table --}}
        @if($rows->isEmpty())
            <x-card>
                <div class="flex flex-col items-center justify-center gap-2 py-10 text-center">
                    <span class="flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <x-icon.package class="size-6" />
                    </span>
                    <p class="font-medium text-foreground">{{ $showArchived ? 'Nothing archived' : 'No stock yet' }}</p>
                    <p class="text-sm text-muted-foreground">{{ $showArchived ? 'Archived lines will appear here.' : 'Receive stock or fulfil a purchase order to build inventory.' }}</p>
                </div>
            </x-card>
        @else
            <div class="overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead class="border-b border-border bg-secondary/40">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Wine</th>
                            <th class="px-3 py-2 text-center text-xs font-medium uppercase tracking-wide text-muted-foreground">Quantity</th>
                            <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wide text-muted-foreground">Last price</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Received</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Files</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($rows as $row)
                            @php($item = $row['item'])
                            @php($product = $row['product'])
                            <tr wire:key="inv-{{ $item->id }}" class="hover:bg-accent/40">
                                <td class="px-3 py-2.5">
                                    <div class="font-medium text-foreground">{{ $product?->wine_name ?? 'Unknown product' }}</div>
                                    @if($product?->producer)
                                        <div class="text-xs text-muted-foreground">{{ $product->producer }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button type="button" wire:click="adjustQuantity({{ $item->id }}, {{ max(0, $item->quantity_units - 1) }})" class="flex size-6 items-center justify-center rounded border border-input text-muted-foreground hover:bg-accent" @disabled($showArchived)>
                                            <x-icon.minus class="size-3.5" />
                                        </button>
                                        <span class="w-8 text-center font-medium tabular-nums">{{ $item->quantity_units }}</span>
                                        <button type="button" wire:click="adjustQuantity({{ $item->id }}, {{ $item->quantity_units + 1 }})" class="flex size-6 items-center justify-center rounded border border-input text-muted-foreground hover:bg-accent" @disabled($showArchived)>
                                            <x-icon.plus class="size-3.5" />
                                        </button>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-muted-foreground">
                                    {{ $item->last_purchase_price !== null ? Currency::format($item->last_purchase_price, $item->last_purchase_currency ?? 'GBP') : '—' }}
                                </td>
                                <td class="px-3 py-2.5 text-muted-foreground">
                                    {{ $item->last_received_at?->format('j M Y') ?? '—' }}
                                </td>
                                <td class="px-3 py-2.5">
                                    @if($canAttachments)
                                        <button type="button" wire:click="openAttachments({{ $item->id }})" class="inline-flex items-center gap-1 text-sm text-primary hover:underline">
                                            <x-icon.paperclip class="size-4" />
                                            {{ count($item->attachments) }}
                                        </button>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-sm text-muted-foreground" title="Pro feature">
                                            <x-icon.lock class="size-3.5" />
                                            {{ count($item->attachments) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    @if($canArchive)
                                        @if($showArchived)
                                            <x-button wire:click="restore({{ $item->id }})" variant="ghost" size="sm">
                                                <x-icon.archive-restore class="size-4" />
                                                Restore
                                            </x-button>
                                        @else
                                            <x-button wire:click="archive({{ $item->id }})" wire:confirm="Archive this line?" variant="ghost" size="sm">
                                                <x-icon.archive class="size-4" />
                                                Archive
                                            </x-button>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif

    {{-- New-venue modal --}}
    <x-modal model="showVenueForm" title="New venue" max-width="md">
        <form wire:submit="createVenue" class="space-y-4">
            <x-input.text name="venueName" label="Venue name" wire:model="venueName" required autofocus hint="e.g. The Cellar Door, Soho" />
            <div class="flex items-center justify-end gap-2 pt-2">
                <x-button type="button" variant="outline" wire:click="$set('showVenueForm', false)">Cancel</x-button>
                <x-button type="submit">Create venue</x-button>
            </div>
        </form>
    </x-modal>

    {{-- Receive-stock modal --}}
    @if($canManualAdd)
        <x-modal model="showAddForm" title="Receive stock" max-width="md">
            <form wire:submit="saveItem" class="space-y-4">
                <div>
                    <x-input.label>Wine</x-input.label>
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="productSearch"
                        placeholder="Search the catalogue…"
                        class="mb-2 mt-1.5 block w-full rounded-md border border-input bg-card px-3 py-2 text-sm shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/40"
                    />
                    <x-input.select name="addProductId" :options="$productOptions" placeholder="Select a wine" wire:model="addProductId" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input.text type="number" name="addQuantity" label="Quantity (bottles)" wire:model="addQuantity" min="1" required />
                    <x-input.text type="number" name="addPrice" label="Unit price (optional)" wire:model="addPrice" step="0.01" min="0" />
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <x-button type="button" variant="outline" wire:click="$set('showAddForm', false)">Cancel</x-button>
                    <x-button type="submit">Receive</x-button>
                </div>
            </form>
        </x-modal>
    @endif

    {{-- Attachments modal --}}
    @if($canAttachments && $attachmentItemId !== null)
        @php($attachmentRow = $rows->first(fn ($r) => $r['item']->id === $attachmentItemId))
        <x-modal model="attachmentItemId" title="Attachments" max-width="lg">
            @if($attachmentRow)
                <div class="space-y-3">
                    @forelse($attachmentRow['item']->attachments as $attachment)
                        <div wire:key="att-{{ $attachment->id }}" class="flex items-center gap-3 border-b border-border pb-3 last:border-0 last:pb-0">
                            <x-icon.file-text class="size-5 shrink-0 text-muted-foreground" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-foreground">{{ $attachment->file_name }}</p>
                                <p class="text-xs text-muted-foreground">{{ number_format($attachment->file_size / 1024, 0) }} KB</p>
                            </div>
                            <a href="{{ route('inventory.attachments.download', $attachment->id) }}" class="text-muted-foreground hover:text-foreground" title="Download">
                                <x-icon.download class="size-4" />
                            </a>
                            <button type="button" wire:click="deleteAttachment({{ $attachment->id }})" wire:confirm="Delete this file?" class="text-muted-foreground hover:text-destructive" title="Delete">
                                <x-icon.trash-2 class="size-4" />
                            </button>
                        </div>
                    @empty
                        <p class="py-4 text-center text-sm text-muted-foreground">No files attached yet.</p>
                    @endforelse
                </div>

                <div class="mt-4 border-t border-border pt-4">
                    <form wire:submit="uploadAttachment" class="space-y-3">
                        <input
                            type="file"
                            wire:model="upload"
                            class="block w-full text-sm text-muted-foreground file:mr-3 file:rounded-md file:border-0 file:bg-secondary file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-secondary-foreground hover:file:bg-secondary/80"
                        />
                        <x-input.error :messages="$errors->get('upload')" />
                        <div class="flex items-center justify-between">
                            <span wire:loading wire:target="upload" class="text-xs text-muted-foreground">Uploading…</span>
                            <x-button type="submit" size="sm" wire:loading.attr="disabled" wire:target="uploadAttachment,upload">
                                <x-icon.upload class="size-4" />
                                Upload
                            </x-button>
                        </div>
                    </form>
                </div>
            @endif
        </x-modal>
    @endif
</div>
