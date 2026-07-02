@use('Domain\Shared\Support\Currency')
@use('Illuminate\Support\Str')

<div class="space-y-6">
    <x-page-header title="New purchase order" subtitle="Search the catalogue, build the lines, and send it as a draft.">
        <x-slot:actions>
            <x-button :href="route('orders')" wire:navigate variant="ghost" size="sm">
                <x-icon.chevron-right class="size-4 rotate-180" /> Back to orders
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid items-start gap-6 lg:grid-cols-3">
        {{-- Lines --}}
        <div class="space-y-4 lg:col-span-2">
            <div>
                <x-input.label for="product-search">Add wines</x-input.label>
                <div class="relative mt-1.5">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-muted-foreground"><x-icon.search class="size-4" /></span>
                    <input id="product-search" type="search" wire:model.live.debounce.300ms="productSearch" placeholder="Search the catalogue…" class="block w-full rounded-md border border-input bg-card py-2 pl-9 pr-3 text-sm text-foreground shadow-sm transition placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring/40" />
                </div>
                @if($productSearch !== '' && $productOptions !== [])
                    <div class="mt-2 max-h-48 divide-y divide-border overflow-y-auto rounded-md border border-border bg-card">
                        @foreach($productOptions as $pid => $label)
                            <button type="button" wire:click="addLine({{ $pid }})" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-accent">
                                <span>{{ $label }}</span>
                                <x-icon.plus class="size-4 text-muted-foreground" />
                            </button>
                        @endforeach
                    </div>
                @elseif($productSearch !== '')
                    <p class="mt-2 text-sm text-muted-foreground">No connected supplier carries a wine matching “{{ $productSearch }}”.</p>
                @endif
                <x-input.error :messages="$errors->get('lines')" />
            </div>

            @if($lines === [])
                <div class="rounded-lg border border-dashed border-input bg-card px-6 py-14 text-center">
                    <x-icon.wine class="mx-auto size-7 text-muted-foreground/45" />
                    <p class="mt-2 font-serif text-lg font-semibold">No lines yet</p>
                    <p class="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">Search the catalogue above, or add wines to your basket while browsing and they'll appear here.</p>
                </div>
            @else
                <div class="overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="border-b border-border bg-secondary/40">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Wine</th>
                                <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wide text-muted-foreground">Unit price</th>
                                <th class="px-3 py-2 text-center text-xs font-medium uppercase tracking-wide text-muted-foreground">Quantity</th>
                                <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wide text-muted-foreground">Line total</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach($lines as $i => $line)
                                @php($isCaseLine = ($line['sold_by'] ?? 'bottle') === 'case')
                                @php($caseSize = max(1, (int) ($line['case_size'] ?? 1)))
                                @php($cases = intdiv((int) $line['quantity'], $caseSize))
                                <tr wire:key="line-{{ $i }}">
                                    <td class="max-w-xs px-3 py-2.5">
                                        <span class="block truncate font-medium text-foreground">{{ $line['wine_name'] }}</span>
                                        @if($isCaseLine)
                                            <span class="text-xs text-muted-foreground">Sold by the case ({{ $caseSize }} btl)</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-muted-foreground">
                                        @if($isCaseLine)
                                            {{ Currency::format((float) $line['unit_price'] * $caseSize, $currency) }}<span class="text-xs">/case</span>
                                        @else
                                            {{ Currency::format($line['unit_price'], $currency) }}<span class="text-xs">/btl</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <div class="flex items-center justify-center gap-1.5">
                                            @if($isCaseLine)
                                                <input type="number" min="1" value="{{ $cases }}" wire:change="setLineCases({{ $i }}, $event.target.value)" aria-label="Cases of {{ $line['wine_name'] }}" class="w-16 rounded-md border border-input bg-card px-2 py-1 text-right text-sm focus:outline-none focus:ring-2 focus:ring-ring/40" />
                                                <span class="text-xs text-muted-foreground">{{ Str::plural('case', $cases) }}</span>
                                            @else
                                                <input type="number" min="1" value="{{ $line['quantity'] }}" wire:change="setLineQty({{ $i }}, $event.target.value)" aria-label="Bottles of {{ $line['wine_name'] }}" class="w-16 rounded-md border border-input bg-card px-2 py-1 text-right text-sm focus:outline-none focus:ring-2 focus:ring-ring/40" />
                                                <span class="text-xs text-muted-foreground">btl</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-mono tabular-nums">{{ Currency::format($line['quantity'] * (float) $line['unit_price'], $currency) }}</td>
                                    <td class="px-3 py-2.5 text-right">
                                        <button type="button" wire:click="removeLine({{ $i }})" class="text-muted-foreground hover:text-destructive" title="Remove line"><x-icon.x class="size-4" /></button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Order details + running total --}}
        <x-card title="Order details">
            <div class="space-y-4">
                <x-input.select name="supplierId" label="Supplier" :options="$suppliers->pluck('name', 'id')->all()" placeholder="Select a supplier" wire:model="supplierId" />
                <x-input.select name="venueId" label="Deliver to" :options="$venues->pluck('name', 'id')->all()" placeholder="No venue yet" wire:model="venueId" hint="Receiving into stock needs a venue." />
                <x-input.textarea name="notes" label="Notes (optional)" wire:model="notes" rows="3" />

                <dl class="space-y-1 border-t border-border pt-4 text-sm">
                    <div class="flex items-baseline justify-between">
                        <dt class="text-muted-foreground">{{ count($lines) }} {{ Str::plural('line', count($lines)) }}</dt>
                        <dd class="font-mono text-xs tabular-nums text-muted-foreground">{{ collect($lines)->sum('quantity') }} btl</dd>
                    </div>
                    <div class="flex items-baseline justify-between">
                        <dt class="font-medium text-foreground">Total</dt>
                        <dd class="font-mono text-xl font-semibold tabular-nums">{{ Currency::format($linesTotal, $currency) }}</dd>
                    </div>
                </dl>

                <x-button wire:click="createOrder" wire:loading.attr="disabled" wire:target="createOrder" class="w-full">
                    Create draft order
                </x-button>
            </div>
        </x-card>
    </div>
</div>
