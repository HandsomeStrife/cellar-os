@use('Domain\Shared\Support\Currency')

<div class="space-y-6">
    @if(! $entitled)
        <x-upgrade-gate
            title="Purchase orders are a paid feature"
            message="Build orders, generate PDFs and email them straight to your suppliers."
            plan="Starter"
        />
    @else
        @php($supplierMap = $suppliers->keyBy('id'))
        @php($venueMap = $venues->keyBy('id'))

        <x-page-header title="Orders" subtitle="Build, send and receive purchase orders.">
            <x-slot:actions>
                <x-button wire:click="openCreate">
                    <x-icon.plus class="size-4" />
                    New order
                </x-button>
            </x-slot:actions>
        </x-page-header>

        {{-- Toolbar --}}
        <div class="flex flex-wrap items-center gap-3">
            <select wire:model.live="statusFilter" class="select-field rounded-md border border-input bg-card px-3 py-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/40">
                <option value="">All statuses</option>
                @foreach($statuses as $status)
                    <option value="{{ $status->value }}">{{ $status->getLabel() }}</option>
                @endforeach
            </select>
        </div>

        {{-- List --}}
        @if($orders->total() === 0)
            <x-card>
                <x-empty-state icon="clipboard-list" title="No orders yet" message="Create a purchase order, or add wines to your basket in the catalogue." />
            </x-card>
        @else
            <div class="overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead class="border-b border-border bg-secondary/40">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Order</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Supplier</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Status</th>
                            <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wide text-muted-foreground">Total</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Created</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($orders as $order)
                            <tr wire:key="order-{{ $order->id }}" class="hover:bg-accent/40">
                                <td class="px-3 py-2.5 font-medium">#{{ $order->uuid ? strtoupper(substr($order->uuid, 0, 8)) : $order->id }}</td>
                                <td class="px-3 py-2.5 text-muted-foreground">{{ $supplierMap[$order->supplier_id]->name ?? '–' }}</td>
                                {{-- Status is read-only here — changing an order's lifecycle is an
                                     explicit act in the order view, not a one-misclick list edit. --}}
                                <td class="px-3 py-2.5">
                                    <x-badge :color="$order->status->getColour()">{{ $order->status->getLabel() }}</x-badge>
                                </td>
                                <td class="px-3 py-2.5 text-right tabular-nums">{{ Currency::format($order->total, data_get($order->items, '0.currency_at_order', $currency)) }}</td>
                                <td class="px-3 py-2.5 text-muted-foreground">{{ $order->created_at?->format('j M Y') }}</td>
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center justify-end gap-1">
                                        <x-button wire:click="$set('viewingId', {{ $order->id }})" variant="ghost" size="sm">View</x-button>
                                        <a href="{{ route('orders.pdf', $order->id) }}" class="flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground" title="Download PDF">
                                            <x-icon.download class="size-4" />
                                        </a>
                                        @if($order->status === \Domain\Order\Enums\OrderStatus::Sent)
                                            <button type="button" wire:click="receive({{ $order->id }})" wire:confirm="Receive this order into inventory?" class="flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground" title="Receive into inventory">
                                                <x-icon.package class="size-4" />
                                            </button>
                                        @endif
                                        @if($canEmail)
                                            <button type="button" wire:click="sendEmail({{ $order->id }})" wire:confirm="Email this order to the supplier?" class="flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground" title="Email to supplier">
                                                <x-icon.mail class="size-4" />
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div>{{ $orders->links() }}</div>
        @endif

        {{-- View modal --}}
        @if($viewing !== null)
            <x-modal model="viewingId" title="Order #{{ $viewing->uuid ? strtoupper(substr($viewing->uuid, 0, 8)) : $viewing->id }}" max-width="2xl">
                <div class="space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <div>
                            <span class="text-muted-foreground">Supplier:</span>
                            <span class="font-medium">{{ $supplierMap[$viewing->supplier_id]->name ?? '–' }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <label for="order-status" class="text-xs uppercase tracking-wider text-muted-foreground">Status</label>
                            <select id="order-status" wire:change="setStatus({{ $viewing->id }}, $event.target.value)" class="select-field rounded-md border border-input bg-card px-2 py-1 text-xs shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/40">
                                @foreach($statuses as $status)
                                    <option value="{{ $status->value }}" @selected($status === $viewing->status)>{{ $status->getLabel() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-md border border-border">
                        <table class="w-full text-sm">
                            <thead class="border-b border-border bg-secondary/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th class="px-3 py-2">Wine</th>
                                    <th class="px-3 py-2 text-right">Qty</th>
                                    <th class="px-3 py-2 text-right">Unit</th>
                                    <th class="px-3 py-2 text-right">Line</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach($viewing->items as $item)
                                    <tr>
                                        <td class="px-3 py-2">{{ $item->wine_name }}</td>
                                        <td class="px-3 py-2 text-right">
                                            @if($item->soldByCaseAtOrder())
                                                {{ $item->casesAtOrder() }} {{ \Illuminate\Support\Str::plural('case', $item->casesAtOrder()) }}@if($item->looseBottlesAtOrder()) + {{ $item->looseBottlesAtOrder() }} btl @endif
                                                <div class="text-xs text-muted-foreground">{{ $item->quantity_units }} btl</div>
                                            @else
                                                {{ $item->quantity_units }}
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-right tabular-nums">
                                            @if($item->soldByCaseAtOrder())
                                                {{ Currency::format($item->casePriceAtOrder(), $item->currency_at_order) }}<span class="text-xs text-muted-foreground">/case</span>
                                            @else
                                                {{ Currency::format($item->unit_price_at_order, $item->currency_at_order) }}
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ Currency::format($item->lineTotal(), $item->currency_at_order) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($viewing->notes)
                        <p class="rounded-md bg-secondary/40 p-3 text-sm text-muted-foreground">{{ $viewing->notes }}</p>
                    @endif

                    <div class="flex items-center justify-between border-t border-border pt-4">
                        <x-button wire:click="deleteOrder({{ $viewing->id }})" wire:confirm="Delete this order?" variant="ghost" size="sm" class="text-destructive hover:bg-destructive/10">
                            <x-icon.trash-2 class="size-4" /> Delete
                        </x-button>
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-muted-foreground">Total</span>
                            <span class="font-serif text-xl font-semibold">{{ Currency::format($viewing->total, data_get($viewing->items, '0.currency_at_order', $currency)) }}</span>
                        </div>
                    </div>
                </div>
            </x-modal>
        @endif

        {{-- Create modal --}}
        <x-modal model="showCreate" title="New purchase order" max-width="2xl">
            <form wire:submit="createOrder" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input.select name="supplierId" label="Supplier" :options="$suppliers->pluck('name', 'id')->all()" placeholder="Select a supplier" wire:model="supplierId" />
                    <x-input.select name="venueId" label="Deliver to (optional)" :options="$venues->pluck('name', 'id')->all()" placeholder="No venue" wire:model="venueId" />
                </div>

                {{-- Add wines --}}
                <div>
                    <x-input.label>Add wines</x-input.label>
                    <div class="mt-1.5 flex gap-2">
                        <input type="search" wire:model.live.debounce.300ms="productSearch" placeholder="Search catalogue…" class="block w-full rounded-md border border-input bg-card px-3 py-2 text-sm shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/40" />
                    </div>
                    @if($productSearch !== '' && $productOptions !== [])
                        <div class="mt-2 max-h-40 divide-y divide-border overflow-y-auto rounded-md border border-border">
                            @foreach($productOptions as $pid => $label)
                                <button type="button" wire:click="addLine({{ $pid }})" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-accent">
                                    <span>{{ $label }}</span>
                                    <x-icon.plus class="size-4 text-muted-foreground" />
                                </button>
                            @endforeach
                        </div>
                    @endif
                    <x-input.error :messages="$errors->get('lines')" />
                </div>

                {{-- Lines --}}
                @if($lines !== [])
                    <div class="space-y-2 rounded-md border border-border p-3">
                        @foreach($lines as $i => $line)
                            @php($isCaseLine = ($line['sold_by'] ?? 'bottle') === 'case')
                            @php($caseSize = max(1, (int) ($line['case_size'] ?? 1)))
                            <div wire:key="line-{{ $i }}" class="flex items-center gap-3">
                                <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ $line['wine_name'] }}</span>
                                @if($isCaseLine)
                                    <span class="text-xs text-muted-foreground">{{ Currency::format((float) $line['unit_price'] * $caseSize, $currency) }}/case</span>
                                    <input type="number" min="1" value="{{ intdiv((int) $line['quantity'], $caseSize) }}" wire:change="setLineCases({{ $i }}, $event.target.value)" class="w-16 rounded-md border border-input bg-card px-2 py-1 text-right text-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/40" />
                                    <span class="text-xs text-muted-foreground">{{ \Illuminate\Support\Str::plural('case', intdiv((int) $line['quantity'], $caseSize)) }}</span>
                                @else
                                    <span class="text-xs text-muted-foreground">{{ Currency::format($line['unit_price'], $currency) }}</span>
                                    <input type="number" min="1" value="{{ $line['quantity'] }}" wire:change="setLineQty({{ $i }}, $event.target.value)" class="w-16 rounded-md border border-input bg-card px-2 py-1 text-right text-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/40" />
                                @endif
                                <button type="button" wire:click="removeLine({{ $i }})" class="text-muted-foreground hover:text-destructive"><x-icon.x class="size-4" /></button>
                            </div>
                        @endforeach
                        <div class="flex items-center justify-end gap-2 border-t border-border pt-2 text-sm">
                            <span class="text-muted-foreground">Total</span>
                            <span class="font-semibold">{{ Currency::format($linesTotal, $currency) }}</span>
                        </div>
                    </div>
                @endif

                <x-input.textarea name="notes" label="Notes (optional)" wire:model="notes" rows="2" />

                <div class="flex items-center justify-end gap-2 pt-2">
                    <x-button type="button" variant="outline" wire:click="$set('showCreate', false)">Cancel</x-button>
                    <x-button type="submit">Create order</x-button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
