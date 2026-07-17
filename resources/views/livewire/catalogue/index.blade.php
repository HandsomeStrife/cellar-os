@use('Domain\Shared\Support\Currency')

<div class="space-y-6">
    <x-page-header title="Catalogue" subtitle="Wines from the suppliers you're connected to." />

    {{-- Toolbar --}}
    @php($inputClasses = 'block w-full rounded-md border border-input bg-card px-3 py-2 text-sm text-foreground shadow-sm transition placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring/40')
    @php($selectClasses = 'select-field block w-full rounded-md border border-input bg-card px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-2 focus:ring-ring/40')
    <div x-data="{ filtersOpen: @js($filterCount > 0) }" class="space-y-3">
        <div class="flex flex-wrap items-center gap-3">
            <div class="relative w-full max-w-xs">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-muted-foreground">
                    <x-icon.search class="size-4" />
                </span>
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search wine or producer…"
                    class="block w-full rounded-md border border-input bg-card py-2 pl-9 pr-3 text-sm text-foreground shadow-sm transition placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring/40"
                />
            </div>

            @if($connectedSuppliers->isNotEmpty())
                <select wire:model.live="supplierFilter" class="select-field rounded-md border border-input bg-card px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-2 focus:ring-ring/40">
                    <option value="">All my suppliers</option>
                    @foreach($connectedSuppliers as $connectedSupplier)
                        <option value="{{ $connectedSupplier->id }}">{{ $connectedSupplier->name }}</option>
                    @endforeach
                </select>
            @endif

            <x-button type="button" variant="outline" @click="filtersOpen = !filtersOpen" x-bind:aria-expanded="filtersOpen.toString()">
                <x-icon.sliders-horizontal class="size-4" />
                Filters
                @if($filterCount > 0)
                    <span class="ml-1 inline-flex min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-xs font-semibold text-primary-foreground">{{ $filterCount }}</span>
                @endif
            </x-button>

            {{-- Column picker: which optional table columns this user sees. --}}
            <div x-data="{ colsOpen: false }" x-on:keydown.escape="colsOpen = false" class="relative">
                <x-button type="button" variant="outline" @click="colsOpen = ! colsOpen" x-bind:aria-expanded="colsOpen.toString()" aria-haspopup="menu">
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

            <div class="ml-auto">
                <x-button wire:click="$set('showBasket', true)" variant="outline">
                    <x-icon.clipboard-list class="size-4" />
                    Basket
                    @if($basketCount > 0)
                        <span class="ml-1 inline-flex min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-xs font-semibold text-primary-foreground">{{ $basketCount }}</span>
                    @endif
                </x-button>
            </div>
        </div>

        {{-- Expandable filter panel (every filterable column) --}}
        <div x-show="filtersOpen" x-cloak x-transition.opacity class="rounded-lg border border-border bg-card p-4 shadow-sm">
            <div class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2 lg:grid-cols-4">
                <label class="block">
                    <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-muted-foreground">Colour</span>
                    <select wire:model.live="colour" class="{{ $selectClasses }}">
                        <option value="">All colours</option>
                        @foreach($colours as $colourOption)
                            <option value="{{ $colourOption->value }}">{{ $colourOption->getLabel() }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-muted-foreground">Country</span>
                    <select wire:model.live="country" class="{{ $selectClasses }}">
                        <option value="">All countries</option>
                        @foreach($countries as $countryOption)
                            <option value="{{ $countryOption }}">{{ $countryOption }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-muted-foreground">Region</span>
                    <select wire:model.live="region" class="{{ $selectClasses }}">
                        <option value="">All regions</option>
                        @foreach($regions as $regionOption)
                            <option value="{{ $regionOption }}">{{ $regionOption }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-muted-foreground">Sub-region</span>
                    <select wire:model.live="sub_region" class="{{ $selectClasses }}">
                        <option value="">All sub-regions</option>
                        @foreach($subRegions as $subRegionOption)
                            <option value="{{ $subRegionOption }}">{{ $subRegionOption }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-muted-foreground">Producer</span>
                    <input type="text" wire:model.live.debounce.400ms="producer" placeholder="Any producer" class="{{ $inputClasses }}" />
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-muted-foreground">Grape</span>
                    <input type="text" wire:model.live.debounce.400ms="grape" placeholder="e.g. Pinot Noir" class="{{ $inputClasses }}" />
                </label>

                <div class="block">
                    <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-muted-foreground">Price ({{ Currency::symbol($currency) }})</span>
                    <div class="flex items-center gap-2">
                        <input type="number" min="0" step="0.01" inputmode="decimal" wire:model.live.debounce.400ms="priceMin" placeholder="Min" class="{{ $inputClasses }}" />
                        <span class="text-muted-foreground">–</span>
                        <input type="number" min="0" step="0.01" inputmode="decimal" wire:model.live.debounce.400ms="priceMax" placeholder="Max" class="{{ $inputClasses }}" />
                    </div>
                </div>

                <div class="block">
                    <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-muted-foreground">Vintage</span>
                    <div class="flex items-center gap-2">
                        <input type="number" min="1900" max="2100" step="1" inputmode="numeric" wire:model.live.debounce.400ms="vintageMin" placeholder="From" class="{{ $inputClasses }}" />
                        <span class="text-muted-foreground">–</span>
                        <input type="number" min="1900" max="2100" step="1" inputmode="numeric" wire:model.live.debounce.400ms="vintageMax" placeholder="To" class="{{ $inputClasses }}" />
                    </div>
                </div>
            </div>

            <div class="mt-3 flex items-center justify-end gap-2">
                @if($filterCount > 0)
                    <x-button type="button" variant="ghost" size="sm" wire:click="resetFilters">
                        <x-icon.x class="size-4" /> Clear filters
                    </x-button>
                @endif
            </div>
        </div>
    </div>

    {{-- Table --}}
    @if($products->total() === 0)
        <x-card>
            <x-empty-state
                icon="wine"
                :title="$hasConnections ? 'No wines found' : 'No suppliers connected yet'"
                :message="$hasConnections
                    ? 'Adjust your filters, or add more suppliers.'
                    : 'Your catalogue shows wines from the suppliers you work with. Add a supplier to get started.'"
            >
                @if(! $hasConnections)
                    <x-button :href="route('suppliers')" wire:navigate variant="outline" size="sm"><x-icon.plus class="size-4" /> Add a supplier</x-button>
                @endif
            </x-empty-state>
        </x-card>
    @else
        <div class="relative">
            {{-- Loading veil: any browse-affecting request dims the results and
                 blocks interaction so the result swap reads as deliberate. --}}
            <div
                wire:loading.flex
                wire:target="search, colour, supplierFilter, country, region, sub_region, producer, grape, priceMin, priceMax, vintageMin, vintageMax, sortBy, resetFilters, gotoPage, nextPage, previousPage"
                class="absolute inset-0 z-10 hidden items-center justify-center rounded-lg bg-card/60"
            >
                <x-icon.loader-circle class="size-6 animate-spin text-primary" />
            </div>

            {{-- Small screens: a stacked list — name + price first, no sideways scrolling. --}}
            <div class="divide-y divide-border rounded-lg border border-border bg-card shadow-sm sm:hidden">
                @foreach($products as $product)
                    @php($fill = $enriched[$product->id] ?? [])
                    @php($rowColour = $product->colour ?? ($fill['colour']['value'] ?? null))
                    @php($rowCountry = $product->country ?: ($fill['country']['value'] ?? null))
                    <div wire:key="m-product-{{ $product->id }}" class="flex items-center gap-3 p-3">
                        <button type="button" wire:click="showWine({{ $product->id }})" class="min-w-0 flex-1 text-left">
                            <p class="truncate text-sm font-medium text-foreground">{{ $product->wine_name }}</p>
                            @if($product->producer)
                                <p class="truncate text-xs text-muted-foreground">{{ $product->producer }}</p>
                            @endif
                            <p class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-xs text-muted-foreground">
                                @if($rowColour)
                                    <span class="inline-flex items-center gap-1">
                                        <span class="size-2 rounded-full ring-1 ring-border dark:ring-white/30" style="background-color: {{ $rowColour->getSwatch() }}"></span>
                                        {{ $rowColour->getLabel() }}
                                    </span>
                                    <span aria-hidden="true">·</span>
                                @endif
                                @if($rowCountry){{ $rowCountry }} <span aria-hidden="true">·</span>@endif
                                {{ $product->vintage ?? 'NV' }} <span aria-hidden="true">·</span> {{ $product->format_ml }}ml
                            </p>
                        </button>
                        <div class="shrink-0 text-right">
                            <p class="whitespace-nowrap text-sm font-medium text-foreground">
                                {{ $product->displayPrice() !== null ? Currency::format($product->displayPrice(), $currency) : '–' }}
                                @if($product->displayPrice() !== null)<span class="text-xs font-normal text-muted-foreground">{{ $product->soldByCase() ? '/case' : '/btl' }}</span>@endif
                            </p>
                            @if($product->perBottleEquivalent() !== null)
                                <p class="text-xs text-muted-foreground">≈ {{ Currency::format($product->perBottleEquivalent(), $currency) }} /btl</p>
                            @endif
                        </div>
                        <x-button wire:click="addToBasket({{ $product->id }})" wire:loading.attr="disabled" wire:target="addToBasket({{ $product->id }})" variant="ghost" size="sm" title="Add to basket">
                            <x-icon.plus class="size-4" />
                        </x-button>
                    </div>
                @endforeach
            </div>

            {{-- The actions column is pinned so the basket "+" stays reachable
                 however many columns are on (the sticky cells need opaque
                 colour-mix backgrounds to mask rows scrolling beneath them). --}}
            @php($stickyHead = 'sticky right-0 z-10 bg-[color-mix(in_srgb,hsl(var(--secondary))_40%,hsl(var(--card)))]')
            @php($stickyCell = 'sticky right-0 bg-card group-hover/row:bg-[color-mix(in_srgb,hsl(var(--accent))_40%,hsl(var(--card)))]')
            <div class="hidden overflow-x-auto rounded-lg border border-border bg-card shadow-sm sm:block">
            <table class="w-full text-sm">
                <thead class="border-b border-border bg-secondary/40">
                    <tr>
                        <x-th-sort column="wine_name" :sort="$sort" :direction="$direction">Wine</x-th-sort>
                        @if(in_array('country', $visibleColumns, true))
                            <x-th-sort column="country" :sort="$sort" :direction="$direction">Country</x-th-sort>
                        @endif
                        @if(in_array('region', $visibleColumns, true))
                            <x-th-sort column="region" :sort="$sort" :direction="$direction">Region</x-th-sort>
                        @endif
                        @if(in_array('grapes', $visibleColumns, true))
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Grapes</th>
                        @endif
                        @if(in_array('colour', $visibleColumns, true))
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Colour</th>
                        @endif
                        @if(in_array('vintage', $visibleColumns, true))
                            <x-th-sort column="vintage" :sort="$sort" :direction="$direction">Vintage</x-th-sort>
                        @endif
                        @if(in_array('format', $visibleColumns, true))
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Format</th>
                        @endif
                        <x-th-sort column="unit_price" :sort="$sort" :direction="$direction" align="right">Price</x-th-sort>
                        <th class="px-3 py-2 {{ $stickyHead }}"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($products as $product)
                        @php($fill = $enriched[$product->id] ?? [])
                        <tr wire:key="product-{{ $product->id }}" class="group/row hover:bg-accent/40">
                            <td class="px-3 py-2.5">
                                <button type="button" wire:click="showWine({{ $product->id }})" class="text-left font-medium text-foreground transition hover:text-primary">
                                    {{ $product->wine_name }}
                                </button>
                                @if($product->producer)
                                    <div class="text-xs text-muted-foreground">{{ $product->producer }}</div>
                                @endif
                            </td>
                            @if(in_array('country', $visibleColumns, true))
                                <td class="px-3 py-2.5 text-muted-foreground">
                                    @if($product->country)
                                        {{ $product->country }}
                                    @elseif(isset($fill['country']))
                                        <x-enriched-fact :source="$fill['country']['source']">{{ $fill['country']['value'] }}</x-enriched-fact>
                                    @else
                                        –
                                    @endif
                                </td>
                            @endif
                            @if(in_array('region', $visibleColumns, true))
                                <td class="px-3 py-2.5 text-muted-foreground">
                                    @if($product->region)
                                        {{ $product->region }}
                                    @elseif(isset($fill['region']))
                                        <x-enriched-fact :source="$fill['region']['source']">{{ $fill['region']['value'] }}</x-enriched-fact>
                                    @else
                                        –
                                    @endif
                                    @if($product->sub_region)
                                        <span class="text-xs text-muted-foreground/80">{{ $product->sub_region }}</span>
                                    @endif
                                </td>
                            @endif
                            @if(in_array('grapes', $visibleColumns, true))
                                <td class="px-3 py-2.5 text-muted-foreground">
                                    @if($product->grape)
                                        {{ implode(', ', $product->grape) }}
                                    @elseif(isset($fill['grape']))
                                        <x-enriched-fact :source="$fill['grape']['source']">{{ implode(', ', $fill['grape']['value']) }}</x-enriched-fact>
                                    @else
                                        –
                                    @endif
                                </td>
                            @endif
                            @if(in_array('colour', $visibleColumns, true))
                                <td class="px-3 py-2.5">
                                    @if($product->colour)
                                        <span class="inline-flex items-center gap-1.5 whitespace-nowrap">
                                            <span class="size-3 rounded-full ring-1 ring-border dark:ring-white/30" style="background-color: {{ $product->colour->getSwatch() }}"></span>
                                            {{ $product->colour->getLabel() }}
                                        </span>
                                    @elseif(isset($fill['colour']))
                                        <x-enriched-fact :source="$fill['colour']['source']">
                                            <span class="inline-flex items-center gap-1.5 whitespace-nowrap">
                                                <span class="size-3 rounded-full ring-1 ring-border dark:ring-white/30" style="background-color: {{ $fill['colour']['value']->getSwatch() }}"></span>
                                                {{ $fill['colour']['value']->getLabel() }}
                                            </span>
                                        </x-enriched-fact>
                                    @else
                                        –
                                    @endif
                                </td>
                            @endif
                            @if(in_array('vintage', $visibleColumns, true))
                                <td class="px-3 py-2.5 text-muted-foreground">{{ $product->vintage ?? 'NV' }}</td>
                            @endif
                            @if(in_array('format', $visibleColumns, true))
                                <td class="px-3 py-2.5 whitespace-nowrap text-muted-foreground">{{ $product->format_ml }}ml · {{ $product->case_size }}/case</td>
                            @endif
                            <td class="px-3 py-2.5 text-right">
                                @if($editingPriceId === $product->id)
                                    <div class="flex items-center justify-end gap-1">
                                        <span class="text-muted-foreground">{{ Currency::symbol($currency) }}</span>
                                        <input
                                            type="number" step="0.01" min="0"
                                            wire:model="priceInput"
                                            wire:keydown.enter="savePrice"
                                            wire:keydown.escape="cancelEditPrice"
                                            class="w-24 rounded-md border border-input bg-card px-2 py-1 text-right text-sm focus:outline-none focus:ring-2 focus:ring-ring/40"
                                            autofocus
                                        />
                                        <button type="button" wire:click="savePrice" wire:loading.attr="disabled" wire:target="savePrice" class="text-primary hover:text-primary/80" title="Save"><x-icon.check class="size-4" /></button>
                                        <button type="button" wire:click="cancelEditPrice" class="text-muted-foreground hover:text-foreground" title="Cancel"><x-icon.x class="size-4" /></button>
                                    </div>
                                @elseif(in_array($product->supplier_id, $editableSupplierIds, true))
                                    <button type="button" wire:click="startEditPrice({{ $product->id }}, '{{ $product->unit_price }}')" class="group inline-flex items-center gap-1.5 whitespace-nowrap font-medium text-foreground" title="Edit price (per bottle)">
                                        {{ $product->displayPrice() !== null ? Currency::format($product->displayPrice(), $currency) : '–' }}
                                        {{-- the price basis is always stated — a trade buyer must never guess bottle vs case --}}
                                        @if($product->displayPrice() !== null)<span class="text-xs font-normal text-muted-foreground">{{ $product->soldByCase() ? '/case' : '/btl' }}</span>@endif
                                        <x-icon.pencil class="size-3.5 text-muted-foreground opacity-0 transition group-hover:opacity-100" />
                                    </button>
                                @else
                                    <span class="whitespace-nowrap font-medium text-foreground">
                                        {{ $product->displayPrice() !== null ? Currency::format($product->displayPrice(), $currency) : '–' }}
                                        @if($product->displayPrice() !== null)<span class="text-xs font-normal text-muted-foreground">{{ $product->soldByCase() ? '/case' : '/btl' }}</span>@endif
                                    </span>
                                @endif
                                @if($product->perBottleEquivalent() !== null)
                                    <div class="text-xs text-muted-foreground">≈ {{ Currency::format($product->perBottleEquivalent(), $currency) }} / btl</div>
                                @endif
                                @if($product->price_per_litre)
                                    <div class="text-xs text-muted-foreground">{{ Currency::format($product->price_per_litre, $currency) }}/L</div>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-right {{ $stickyCell }}">
                                <div class="flex items-center justify-end gap-1">
                                    <x-button wire:click="addToBasket({{ $product->id }})" wire:loading.attr="disabled" wire:target="addToBasket({{ $product->id }})" variant="ghost" size="sm" title="Add to basket">
                                        <x-icon.plus class="size-4" wire:loading.remove wire:target="addToBasket({{ $product->id }})" />
                                        <x-icon.loader-circle class="size-4 animate-spin" wire:loading wire:target="addToBasket({{ $product->id }})" />
                                    </x-button>
                                    @if(in_array($product->supplier_id, $editableSupplierIds, true))
                                        <x-button wire:click="deleteProduct({{ $product->id }})" wire:confirm="Delete {{ $product->wine_name }} from the catalogue?" wire:loading.attr="disabled" wire:target="deleteProduct({{ $product->id }})" variant="ghost" size="sm" title="Delete wine" class="text-destructive hover:bg-destructive/10">
                                            <x-icon.trash-2 class="size-4" />
                                        </x-button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>

        <div>{{ $products->links() }}</div>
    @endif

    {{-- Wine detail slideover --}}
    <x-slideover model="showDetail" max-width="lg">
        <x-slot:header>
            @if($detail)
                <h2 class="font-serif text-xl font-semibold leading-snug">{{ $detail->wine_name }}</h2>
                @if($detail->producer)
                    <p class="mt-0.5 text-sm text-muted-foreground">{{ $detail->producer }}</p>
                @endif
            @else
                <h2 class="font-serif text-xl font-semibold">Wine details</h2>
            @endif
        </x-slot:header>

        @if($detail)
            <div class="space-y-6">
                {{-- Price --}}
                <div class="rounded-lg border border-border bg-secondary/40 px-4 py-3">
                    <p class="font-mono text-xs uppercase tracking-[0.18em] text-muted-foreground">{{ $detail->soldByCase() ? 'Price per case' : 'Price per bottle' }}</p>
                    <p class="mt-1 font-serif text-2xl font-semibold">
                        {{ $detail->displayPrice() !== null ? Currency::format($detail->displayPrice(), $currency) : '–' }}
                    </p>
                    <p class="mt-0.5 space-x-3 text-xs text-muted-foreground">
                        @if($detail->perBottleEquivalent() !== null)
                            <span>≈ {{ Currency::format($detail->perBottleEquivalent(), $currency) }} / bottle</span>
                        @endif
                        @if($detail->price_per_litre)
                            <span>{{ Currency::format($detail->price_per_litre, $currency) }} / litre</span>
                        @endif
                    </p>
                </div>

                {{-- Attributes --}}
                <dl class="divide-y divide-border text-sm">
                    <div class="flex items-start justify-between gap-4 py-2.5">
                        <dt class="shrink-0 text-muted-foreground">Supplier</dt>
                        <dd class="text-right font-medium text-foreground">{{ $detailSupplier?->name ?? '–' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 py-2.5">
                        <dt class="shrink-0 text-muted-foreground">Colour</dt>
                        <dd class="text-right">
                            @if($detail->colour)
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="size-3 rounded-full ring-1 ring-border dark:ring-white/30" style="background-color: {{ $detail->colour->getSwatch() }}"></span>
                                    {{ $detail->colour->getLabel() }}
                                </span>
                            @elseif(isset($detailFill['colour']))
                                <x-enriched-fact :source="$detailFill['colour']['source']">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="size-3 rounded-full ring-1 ring-border dark:ring-white/30" style="background-color: {{ $detailFill['colour']['value']->getSwatch() }}"></span>
                                        {{ $detailFill['colour']['value']->getLabel() }}
                                    </span>
                                </x-enriched-fact>
                            @else
                                –
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 py-2.5">
                        <dt class="shrink-0 text-muted-foreground">Grapes</dt>
                        <dd class="text-right">
                            @if($detail->grape)
                                {{ implode(', ', $detail->grape) }}
                            @elseif(isset($detailFill['grape']))
                                <x-enriched-fact :source="$detailFill['grape']['source']">{{ implode(', ', $detailFill['grape']['value']) }}</x-enriched-fact>
                            @else
                                –
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 py-2.5">
                        <dt class="shrink-0 text-muted-foreground">Country</dt>
                        <dd class="text-right">
                            @if($detail->country)
                                {{ $detail->country }}
                            @elseif(isset($detailFill['country']))
                                <x-enriched-fact :source="$detailFill['country']['source']">{{ $detailFill['country']['value'] }}</x-enriched-fact>
                            @else
                                –
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 py-2.5">
                        <dt class="shrink-0 text-muted-foreground">Region</dt>
                        <dd class="text-right">
                            @if($detail->region)
                                {{ $detail->region }}
                            @elseif(isset($detailFill['region']))
                                <x-enriched-fact :source="$detailFill['region']['source']">{{ $detailFill['region']['value'] }}</x-enriched-fact>
                            @else
                                –
                            @endif
                        </dd>
                    </div>
                    @if($detail->sub_region)
                        <div class="flex items-start justify-between gap-4 py-2.5">
                            <dt class="shrink-0 text-muted-foreground">Sub-region</dt>
                            <dd class="text-right">{{ $detail->sub_region }}</dd>
                        </div>
                    @endif
                    <div class="flex items-start justify-between gap-4 py-2.5">
                        <dt class="shrink-0 text-muted-foreground">Vintage</dt>
                        <dd class="text-right">{{ $detail->vintage ?? 'NV' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 py-2.5">
                        <dt class="shrink-0 text-muted-foreground">Format</dt>
                        <dd class="text-right">{{ $detail->format_ml }}ml</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 py-2.5">
                        <dt class="shrink-0 text-muted-foreground">Sold by</dt>
                        <dd class="text-right">{{ $detail->soldByCase() ? 'Case of '.$detail->case_size : 'Bottle ('.$detail->case_size.'/case)' }}</dd>
                    </div>
                    @if($detail->last_seen_at)
                        <div class="flex items-start justify-between gap-4 py-2.5">
                            <dt class="shrink-0 text-muted-foreground">Last seen in price list</dt>
                            <dd class="text-right">{{ $detail->last_seen_at->format('j M Y') }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        @endif

        <x-slot:footer>
            {{-- Alpine-side close so the slide-out starts instantly, like the X / backdrop. --}}
            <x-button type="button" variant="outline" x-on:click="open = false">Close</x-button>
            @if($detail)
                <x-button wire:click="addToBasket({{ $detail->id }})" wire:loading.attr="disabled" wire:target="addToBasket({{ $detail->id }})">
                    <x-icon.plus class="size-4" />
                    {{ $detail->soldByCase() ? 'Add case to basket' : 'Add to basket' }}
                </x-button>
            @endif
        </x-slot:footer>
    </x-slideover>

    {{-- Basket modal --}}
    <x-modal model="showBasket" title="Order basket" max-width="2xl">
        @if($basketLines->isEmpty())
            <div class="py-8 text-center text-sm text-muted-foreground">
                Your basket is empty. Add wines from the catalogue to build an order.
            </div>
        @else
            <div class="space-y-3">
                @foreach($basketLines as $line)
                    <div wire:key="basket-{{ $line['product']->id }}" class="flex items-center gap-3 border-b border-border pb-3 last:border-0 last:pb-0">
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium text-foreground">{{ $line['product']->wine_name }}</p>
                            @if($line['is_case'])
                                <p class="text-xs text-muted-foreground">
                                    {{ Currency::format($line['case_price'], $currency) }} / case ({{ $line['product']->case_size }} btl)
                                    @if($line['product']->unit_price !== null) · {{ Currency::format($line['product']->unit_price, $currency) }} / btl @endif
                                </p>
                            @else
                                <p class="text-xs text-muted-foreground">
                                    {{ $line['product']->unit_price !== null ? Currency::format($line['product']->unit_price, $currency) : '–' }} / bottle
                                </p>
                            @endif
                        </div>
                        @if($line['is_case'])
                            <div class="flex items-center gap-1">
                                <input
                                    type="number" min="1"
                                    value="{{ $line['cases'] }}"
                                    wire:change="setBasketCases({{ $line['product']->id }}, $event.target.value)"
                                    class="w-16 rounded-md border border-input bg-card px-2 py-1 text-right text-sm focus:outline-none focus:ring-2 focus:ring-ring/40"
                                />
                                <span class="text-xs text-muted-foreground">{{ \Illuminate\Support\Str::plural('case', $line['cases']) }}</span>
                            </div>
                        @else
                            <input
                                type="number" min="1"
                                value="{{ $line['qty'] }}"
                                wire:change="setBasketQty({{ $line['product']->id }}, $event.target.value)"
                                class="w-20 rounded-md border border-input bg-card px-2 py-1 text-right text-sm focus:outline-none focus:ring-2 focus:ring-ring/40"
                            />
                        @endif
                        <div class="w-24 text-right font-medium tabular-nums">{{ Currency::format($line['line_total'], $currency) }}</div>
                        <button type="button" wire:click="removeFromBasket({{ $line['product']->id }})" class="text-muted-foreground hover:text-destructive" title="Remove">
                            <x-icon.trash-2 class="size-4" />
                        </button>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 flex items-center justify-between border-t border-border pt-4">
                <span class="text-sm text-muted-foreground">Total ({{ $basketCount }} {{ \Illuminate\Support\Str::plural('wine', $basketCount) }})</span>
                <span class="font-serif text-xl font-semibold">{{ Currency::format($basketTotal, $currency) }}</span>
            </div>

            <div class="mt-4 flex items-center justify-end gap-2">
                <x-button wire:click="clearBasket" variant="ghost" size="sm" wire:confirm="Clear the basket?">Clear</x-button>
                <x-button variant="outline" wire:click="$set('showBasket', false)">Keep browsing</x-button>
                @if($canCreateOrders)
                    <x-button wire:click="createOrders" wire:loading.attr="disabled" wire:target="createOrders">Create purchase orders</x-button>
                @else
                    <x-button :href="route('pricing')" wire:navigate>Upgrade to order</x-button>
                @endif
            </div>
        @endif
    </x-modal>
</div>
