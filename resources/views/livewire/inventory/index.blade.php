@use('Domain\Shared\Support\Currency')

<div class="space-y-6">
    @if(! $canInventory)
        {{-- Whole feature gated (Starter+) --}}
        <x-upgrade-gate
            title="Inventory is a paid feature"
            message="Track received stock per venue, archive lines, and attach invoices and tasting notes."
            plan="Pro"
        />
    @elseif($venues->isEmpty())
        {{-- No venue yet --}}
        <x-card>
            <x-empty-state icon="building-2" title="Create your first venue" message="Inventory is tracked per venue (a restaurant, bar or store).">
                <x-button wire:click="$set('showVenueForm', true)">
                    <x-icon.plus class="size-4" />
                    New venue
                </x-button>
            </x-empty-state>
        </x-card>
    @else
        <x-page-header title="Inventory" subtitle="Received stock by venue." />

        {{-- Toolbar --}}
        <div class="flex flex-wrap items-center gap-3">
            <select
                wire:change="selectVenue($event.target.value)"
                class="select-field rounded-md border border-input bg-card px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-2 focus:ring-ring/40"
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
                    class="block w-full rounded-md border border-input bg-card py-2 pl-9 pr-3 text-sm text-foreground shadow-sm transition placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring/40"
                />
            </div>

            <div class="ml-auto flex items-center gap-2">
                {{-- Label states the action, not the current state — "Active" alone read as a status. --}}
                {{-- Column picker: which optional wine columns this user sees. --}}
                <div x-data="{ colsOpen: false }" x-on:keydown.escape="colsOpen = false" class="relative">
                    <x-button type="button" variant="outline" size="sm" @click="colsOpen = ! colsOpen" x-bind:aria-expanded="colsOpen.toString()" aria-haspopup="menu">
                        <x-icon.columns-3 class="size-4" />
                        Columns
                    </x-button>
                    <div
                        x-show="colsOpen"
                        x-cloak
                        x-transition
                        x-on:click.outside="colsOpen = false"
                        class="absolute left-0 z-20 mt-2 w-44 rounded-md border border-border bg-popover p-1.5 text-popover-foreground shadow-lg"
                    >
                        @foreach($columns as $columnKey => $columnLabel)
                            <label class="flex cursor-pointer items-center gap-2.5 rounded px-2.5 py-1.5 text-sm transition hover:bg-accent">
                                <input type="checkbox" value="{{ $columnKey }}" wire:model.live="visibleColumns" class="accent-primary" />
                                {{ $columnLabel }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <x-button wire:click="$toggle('showArchived')" variant="{{ $showArchived ? 'secondary' : 'outline' }}" size="sm">
                    <x-icon.archive class="size-4" />
                    {{ $showArchived ? 'Back to active stock' : 'Show archived' }}
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
                <x-empty-state icon="package" :title="$showArchived ? 'Nothing archived' : 'No stock yet'" :message="$showArchived ? 'Archived lines will appear here.' : 'Receive stock or fulfil a purchase order to build inventory.'" />
            </x-card>
        @else
            <div class="overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead class="border-b border-border bg-secondary/40">
                        <tr>
                            @php($th = 'px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground')
                            <th class="{{ $th }}">Wine</th>
                            @if(in_array('producer', $visibleColumns, true))<th class="{{ $th }}">Producer</th>@endif
                            @if(in_array('supplier', $visibleColumns, true))<th class="{{ $th }}">Supplier</th>@endif
                            @if(in_array('country', $visibleColumns, true))<th class="{{ $th }}">Country</th>@endif
                            @if(in_array('region', $visibleColumns, true))<th class="{{ $th }}">Region</th>@endif
                            @if(in_array('grapes', $visibleColumns, true))<th class="{{ $th }}">Grapes</th>@endif
                            @if(in_array('colour', $visibleColumns, true))<th class="{{ $th }}">Type</th>@endif
                            @if(in_array('vintage', $visibleColumns, true))<th class="{{ $th }}">Vintage</th>@endif
                            @if(in_array('format', $visibleColumns, true))<th class="{{ $th }}">Format</th>@endif
                            <th class="px-3 py-2 text-center text-xs font-medium uppercase tracking-wide text-muted-foreground">Quantity</th>
                            @if(in_array('price', $visibleColumns, true))<th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wide text-muted-foreground">Last price</th>@endif
                            @if(in_array('received', $visibleColumns, true))<th class="{{ $th }}">Received</th>@endif
                            @if(in_array('files', $visibleColumns, true))<th class="{{ $th }}">Files</th>@endif
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($rows as $row)
                            @php($item = $row['item'])
                            @php($product = $row['product'])
                            <tr wire:key="inv-{{ $item->id }}" class="hover:bg-accent/40">
                                @php($fill = $product ? ($enriched[$product->id] ?? []) : [])
                                <td class="px-3 py-2.5">
                                    @if($product)
                                        <div class="font-medium text-foreground">{{ $product->wine_name }}</div>
                                        @if($product->producer && ! in_array('producer', $visibleColumns, true))
                                            <div class="text-xs text-muted-foreground">{{ $product->producer }}</div>
                                        @endif
                                    @else
                                        {{-- Degraded row: the catalogue product behind this stock line is gone.
                                             Say so plainly instead of dressing it up as a normal wine. --}}
                                        <div class="flex items-center gap-1.5 font-medium text-muted-foreground">
                                            <x-icon.circle-alert class="size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                                            Product removed from catalogue
                                        </div>
                                        <div class="text-xs text-muted-foreground">Stock line #{{ $item->id }}. The count is still yours; archive it if it's no longer needed.</div>
                                    @endif
                                </td>
                                @if(in_array('producer', $visibleColumns, true))
                                    <td class="px-3 py-2.5 text-muted-foreground">{{ $product?->producer ?: '–' }}</td>
                                @endif
                                @if(in_array('supplier', $visibleColumns, true))
                                    <td class="px-3 py-2.5 text-muted-foreground">{{ $supplierNames[$product?->supplier_id] ?? '–' }}</td>
                                @endif
                                @if(in_array('country', $visibleColumns, true))
                                    <td class="px-3 py-2.5 text-muted-foreground">
                                        @if($product?->country){{ $product->country }}
                                        @elseif(isset($fill['country']))<x-enriched-fact :source="$fill['country']['source']">{{ $fill['country']['value'] }}</x-enriched-fact>
                                        @else – @endif
                                    </td>
                                @endif
                                @if(in_array('region', $visibleColumns, true))
                                    <td class="px-3 py-2.5 text-muted-foreground">
                                        @if($product?->region){{ $product->region }}
                                        @elseif(isset($fill['region']))<x-enriched-fact :source="$fill['region']['source']">{{ $fill['region']['value'] }}</x-enriched-fact>
                                        @else – @endif
                                    </td>
                                @endif
                                @if(in_array('grapes', $visibleColumns, true))
                                    <td class="px-3 py-2.5 text-muted-foreground">
                                        @if($product?->grape){{ implode(', ', $product->grape) }}
                                        @elseif(isset($fill['grape']))<x-enriched-fact :source="$fill['grape']['source']">{{ implode(', ', $fill['grape']['value']) }}</x-enriched-fact>
                                        @else – @endif
                                    </td>
                                @endif
                                @if(in_array('colour', $visibleColumns, true))
                                    @php($rowType = $product?->colour ?? ($fill['colour']['value'] ?? null))
                                    <td class="px-3 py-2.5">
                                        @if($rowType)
                                            <span class="inline-flex items-center gap-1.5 whitespace-nowrap">
                                                <span class="size-3 rounded-full ring-1 ring-border dark:ring-white/30" style="background-color: {{ $rowType->getSwatch() }}"></span>
                                                {{ $rowType->getLabel() }}
                                            </span>
                                            @if($product?->sub_type)
                                                <div class="text-xs text-muted-foreground">{{ $product->sub_type->getShortLabel() }}</div>
                                            @endif
                                        @else
                                            <span class="text-muted-foreground">–</span>
                                        @endif
                                    </td>
                                @endif
                                @if(in_array('vintage', $visibleColumns, true))
                                    <td class="px-3 py-2.5 text-muted-foreground">{{ $product?->vintage ?? ($product ? 'NV' : '–') }}</td>
                                @endif
                                @if(in_array('format', $visibleColumns, true))
                                    <td class="px-3 py-2.5 whitespace-nowrap text-muted-foreground">{{ $product ? $product->format_ml.'ml · '.$product->case_size.'/case' : '–' }}</td>
                                @endif
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
                                @if(in_array('price', $visibleColumns, true))
                                    <td class="px-3 py-2.5 text-right tabular-nums text-muted-foreground">
                                        {{ $item->last_purchase_price !== null ? Currency::format($item->last_purchase_price, $item->last_purchase_currency ?? 'GBP') : '–' }}
                                    </td>
                                @endif
                                @if(in_array('received', $visibleColumns, true))
                                    <td class="px-3 py-2.5 text-muted-foreground">
                                        {{ $item->last_received_at?->format('j M Y') ?? '–' }}
                                    </td>
                                @endif
                                @if(in_array('files', $visibleColumns, true))
                                <td class="px-3 py-2.5">
                                    @if($canAttachments)
                                        {{-- A claret paperclip shouting "0" on every row is noise; quiet until there's something attached. --}}
                                        <button type="button" wire:click="openAttachments({{ $item->id }})" @class([
                                            'inline-flex items-center gap-1 text-sm',
                                            'text-primary hover:underline' => count($item->attachments) > 0,
                                            'text-muted-foreground/60 hover:text-foreground' => count($item->attachments) === 0,
                                        ]) title="{{ count($item->attachments) > 0 ? count($item->attachments).' attached' : 'Attach a file' }}">
                                            <x-icon.paperclip class="size-4" />
                                            @if(count($item->attachments) > 0){{ count($item->attachments) }}@endif
                                        </button>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-sm text-muted-foreground/60" title="Pro feature">
                                            <x-icon.lock class="size-3.5" />
                                        </span>
                                    @endif
                                </td>
                                @endif
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
                        class="mb-2 mt-1.5 block w-full rounded-md border border-input bg-card px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-ring/40"
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
