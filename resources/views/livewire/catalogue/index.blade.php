@use('Domain\Shared\Support\Currency')

<div class="space-y-6">
    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-3">
        <div class="relative w-full max-w-xs">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-muted-foreground">
                <x-icon.search class="size-4" />
            </span>
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Search wine or producer…"
                class="block w-full rounded-md border border-input bg-card py-2 pl-9 pr-3 text-sm text-foreground shadow-sm transition placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/40"
            />
        </div>

        <select wire:model.live="country" class="select-field rounded-md border border-input bg-card px-3 py-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/40">
            <option value="">All countries</option>
            @foreach($countries as $countryOption)
                <option value="{{ $countryOption }}">{{ $countryOption }}</option>
            @endforeach
        </select>

        <select wire:model.live="colour" class="select-field rounded-md border border-input bg-card px-3 py-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/40">
            <option value="">All colours</option>
            @foreach($colours as $colourOption)
                <option value="{{ $colourOption->value }}">{{ $colourOption->getLabel() }}</option>
            @endforeach
        </select>

        @if($connectedSuppliers->isNotEmpty())
            <select wire:model.live="supplierFilter" class="select-field rounded-md border border-input bg-card px-3 py-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/40">
                <option value="">All my suppliers</option>
                @foreach($connectedSuppliers as $connectedSupplier)
                    <option value="{{ $connectedSupplier->id }}">{{ $connectedSupplier->name }}</option>
                @endforeach
            </select>
        @endif

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

    {{-- Table --}}
    @if($products->total() === 0)
        <x-card>
            <div class="flex flex-col items-center justify-center gap-3 py-10 text-center">
                <span class="flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <x-icon.wine class="size-6" />
                </span>
                <div>
                    @if(! $hasConnections)
                        <p class="font-medium text-foreground">No suppliers connected yet</p>
                        <p class="text-sm text-muted-foreground">Your catalogue shows wines from the suppliers you work with. Connect to a supplier to get started.</p>
                    @else
                        <p class="font-medium text-foreground">No wines found</p>
                        <p class="text-sm text-muted-foreground">Adjust your filters, or connect to more suppliers.</p>
                    @endif
                </div>
                @if(! $hasConnections)
                    <x-button :href="route('suppliers')" wire:navigate variant="outline" size="sm"><x-icon.plus class="size-4" /> Find suppliers</x-button>
                @endif
            </div>
        </x-card>
    @else
        <div class="overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
            <table class="w-full text-sm">
                <thead class="border-b border-border bg-secondary/40">
                    <tr>
                        <x-th-sort column="wine_name" :sort="$sort" :direction="$direction">Wine</x-th-sort>
                        <x-th-sort column="country" :sort="$sort" :direction="$direction">Origin</x-th-sort>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Colour</th>
                        <x-th-sort column="vintage" :sort="$sort" :direction="$direction">Vintage</x-th-sort>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Format</th>
                        <x-th-sort column="unit_price" :sort="$sort" :direction="$direction" align="right">Price</x-th-sort>
                        <x-th-sort column="stock" :sort="$sort" :direction="$direction" align="right">Stock</x-th-sort>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($products as $product)
                        <tr wire:key="product-{{ $product->id }}" class="hover:bg-accent/40">
                            <td class="px-3 py-2.5">
                                <div class="font-medium text-foreground">{{ $product->wine_name }}</div>
                                @if($product->producer)
                                    <div class="text-xs text-muted-foreground">{{ $product->producer }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-muted-foreground">
                                {{ $product->country ?? '–' }}@if($product->region)<span class="text-xs"> · {{ $product->region }}</span>@endif
                            </td>
                            <td class="px-3 py-2.5">
                                @if($product->colour)
                                    <span class="inline-flex items-center gap-1.5 whitespace-nowrap">
                                        <span class="size-3 rounded-full ring-1 ring-border" style="background-color: {{ $product->colour->getSwatch() }}"></span>
                                        {{ $product->colour->getLabel() }}
                                    </span>
                                @else
                                    –
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-muted-foreground">{{ $product->vintage ?? 'NV' }}</td>
                            <td class="px-3 py-2.5 whitespace-nowrap text-muted-foreground">{{ $product->format_ml }}ml · {{ $product->case_size }}/case</td>
                            <td class="px-3 py-2.5 text-right">
                                @if($editingPriceId === $product->id)
                                    <div class="flex items-center justify-end gap-1">
                                        <span class="text-muted-foreground">{{ Currency::symbol($currency) }}</span>
                                        <input
                                            type="number" step="0.01" min="0"
                                            wire:model="priceInput"
                                            wire:keydown.enter="savePrice"
                                            wire:keydown.escape="cancelEditPrice"
                                            class="w-24 rounded-md border border-input bg-card px-2 py-1 text-right text-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/40"
                                            autofocus
                                        />
                                        <button type="button" wire:click="savePrice" class="text-primary hover:text-primary/80" title="Save"><x-icon.check class="size-4" /></button>
                                        <button type="button" wire:click="cancelEditPrice" class="text-muted-foreground hover:text-foreground" title="Cancel"><x-icon.x class="size-4" /></button>
                                    </div>
                                @elseif(in_array($product->supplier_id, $editableSupplierIds, true))
                                    <button type="button" wire:click="startEditPrice({{ $product->id }}, '{{ $product->unit_price }}')" class="group inline-flex items-center gap-1.5 whitespace-nowrap font-medium text-foreground" title="Edit price">
                                        {{ $product->unit_price !== null ? Currency::format($product->unit_price, $currency) : '–' }}
                                        <x-icon.pencil class="size-3.5 text-muted-foreground opacity-0 transition group-hover:opacity-100" />
                                    </button>
                                @else
                                    <span class="whitespace-nowrap font-medium text-foreground">{{ $product->unit_price !== null ? Currency::format($product->unit_price, $currency) : '–' }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-right text-muted-foreground">{{ $product->stock }}</td>
                            <td class="px-3 py-2.5 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <x-button wire:click="addToBasket({{ $product->id }})" variant="ghost" size="sm" title="Add to basket">
                                        <x-icon.plus class="size-4" />
                                    </x-button>
                                    @if(in_array($product->supplier_id, $editableSupplierIds, true))
                                        <x-button wire:click="deleteProduct({{ $product->id }})" wire:confirm="Delete {{ $product->wine_name }} from the catalogue?" variant="ghost" size="sm" title="Delete wine" class="text-destructive hover:bg-destructive/10">
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

        <div>{{ $products->links() }}</div>
    @endif

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
                            <p class="text-xs text-muted-foreground">
                                {{ $line['product']->unit_price !== null ? Currency::format($line['product']->unit_price, $currency) : '–' }} / bottle
                            </p>
                        </div>
                        <input
                            type="number" min="1"
                            value="{{ $line['qty'] }}"
                            wire:change="setBasketQty({{ $line['product']->id }}, $event.target.value)"
                            class="w-20 rounded-md border border-input bg-card px-2 py-1 text-right text-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/40"
                        />
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
