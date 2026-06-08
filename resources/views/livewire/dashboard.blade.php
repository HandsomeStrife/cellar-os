@use('Domain\Shared\Support\Currency')

<div class="space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="font-serif text-2xl font-semibold">
                Welcome{{ $user?->full_name ? ', '.\Illuminate\Support\Str::before($user->full_name, ' ') : '' }}
            </h2>
            <p class="mt-1 text-sm text-muted-foreground">Your cellar at a glance.</p>
        </div>
        @if($plan)
            <x-badge color="wine">{{ $plan->getLabel() }} plan</x-badge>
        @endif
    </div>

    {{-- Headline KPIs --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Wines in catalogue" :value="number_format($productCount)" icon="wine" />
        <x-stat label="Bottles in stock" :value="number_format($inventoryBottles)" icon="package">
            <x-slot:footer>{{ number_format($inventoryLabels) }} labels</x-slot:footer>
        </x-stat>
        <x-stat label="Inventory value" :value="Currency::format($inventoryValue, $currency)" icon="credit-card" />
        <x-stat label="Active suppliers" :value="number_format($activeSuppliers)" icon="users">
            <x-slot:footer>{{ number_format($supplierCount) }} total</x-slot:footer>
        </x-stat>
    </div>

    {{-- Secondary metrics --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Purchase orders" :value="number_format($orderCount)" icon="clipboard-list" />
        <x-stat label="Open orders" :value="number_format($openOrderCount)" icon="clipboard-list" />
        <div @class(['rounded-lg border bg-card p-5 shadow-sm', 'border-amber-400/50' => $lowStockCount > 0, 'border-border' => $lowStockCount === 0])>
            <p class="text-sm font-medium text-muted-foreground">Low stock</p>
            <p class="mt-2 font-serif text-3xl font-semibold {{ $lowStockCount > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-foreground' }}">{{ number_format($lowStockCount) }}</p>
        </div>
        <div @class(['rounded-lg border bg-card p-5 shadow-sm', 'border-destructive/50' => $outOfStockCount > 0, 'border-border' => $outOfStockCount === 0])>
            <p class="text-sm font-medium text-muted-foreground">Out of stock</p>
            <p class="mt-2 font-serif text-3xl font-semibold {{ $outOfStockCount > 0 ? 'text-destructive' : 'text-foreground' }}">{{ number_format($outOfStockCount) }}</p>
        </div>
    </div>

    @if($inventoryBottles > 0)
        <div class="grid gap-4 lg:grid-cols-2">
            {{-- Inventory by colour --}}
            <x-card title="Inventory by colour">
                <div class="space-y-2.5">
                    @php
                        $maxColour = max($byColour ?: [1]);
                    @endphp
                    @foreach($byColour as $colour => $qty)
                        <div>
                            <div class="mb-1 flex items-center justify-between text-sm">
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="size-3 rounded-full ring-1 ring-border" style="background-color: {{ $colourSwatch($colour) }}"></span>
                                    {{ $colour }}
                                </span>
                                <span class="text-muted-foreground tabular-nums">{{ number_format($qty) }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-secondary">
                                <div class="h-full rounded-full" style="width: {{ $maxColour > 0 ? round($qty / $maxColour * 100) : 0 }}%; background-color: {{ $colourSwatch($colour) }}"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-card>

            {{-- Inventory by country --}}
            <x-card title="Inventory by country">
                <ul class="space-y-2.5 text-sm">
                    @foreach($byCountry as $country => $data)
                        <div>
                            <div class="mb-1 flex items-center justify-between">
                                <span>{{ $country }}</span>
                                <span class="text-muted-foreground tabular-nums">{{ number_format($data['count']) }} btl · {{ Currency::format($data['value'], $currency) }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-secondary">
                                <div class="h-full rounded-full bg-primary" style="width: {{ $inventoryBottles > 0 ? round($data['count'] / $inventoryBottles * 100) : 0 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </ul>
            </x-card>
        </div>

        @if($topRegions !== [])
            <x-card title="Top regions">
                @php
                    $maxRegion = max($topRegions ?: [1]);
                @endphp
                <div class="grid gap-x-6 gap-y-2.5 sm:grid-cols-2">
                    @foreach($topRegions as $region => $qty)
                        <div>
                            <div class="mb-1 flex items-center justify-between text-sm">
                                <span>{{ $region }}</span>
                                <span class="text-muted-foreground tabular-nums">{{ number_format($qty) }} btl</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-secondary">
                                <div class="h-full rounded-full bg-primary/70" style="width: {{ $maxRegion > 0 ? round($qty / $maxRegion * 100) : 0 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-card>
        @endif

        <div class="grid gap-4 lg:grid-cols-2">
            {{-- Recent orders --}}
            <x-card title="Recent orders">
                <x-slot:header>
                    <div class="flex items-center justify-between">
                        <h3 class="font-serif text-lg font-semibold">Recent orders</h3>
                        <a href="{{ route('orders') }}" class="text-sm text-primary hover:underline" wire:navigate>View all</a>
                    </div>
                </x-slot:header>
                @forelse($recentOrders as $order)
                    <div wire:key="recent-{{ $order['id'] }}" class="flex items-center justify-between border-b border-border py-2 text-sm last:border-0">
                        <div>
                            <p class="font-medium">{{ $order['supplier'] }}</p>
                            <p class="text-xs text-muted-foreground">{{ $order['items'] }} {{ \Illuminate\Support\Str::plural('line', $order['items']) }} · {{ $order['created_at']?->format('j M Y') }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="tabular-nums">{{ Currency::format($order['total'], $currency) }}</span>
                            <x-badge :color="$order['status']->getColour()">{{ $order['status']->getLabel() }}</x-badge>
                        </div>
                    </div>
                @empty
                    <p class="py-4 text-center text-sm text-muted-foreground">No orders yet.</p>
                @endforelse
            </x-card>

            {{-- Low stock alerts --}}
            <x-card title="Low stock alerts">
                @if($lowStockItems === [])
                    <p class="py-4 text-center text-sm text-muted-foreground">Nothing running low.</p>
                @else
                    <div class="space-y-2">
                        @foreach($lowStockItems as $item)
                            <div class="flex items-center justify-between text-sm">
                                <div class="min-w-0">
                                    <p class="truncate font-medium">{{ $item['name'] }}</p>
                                    @if($item['producer'])<p class="truncate text-xs text-muted-foreground">{{ $item['producer'] }}</p>@endif
                                </div>
                                <x-badge :color="$item['qty'] <= 3 ? 'red' : 'amber'">{{ $item['qty'] }} left</x-badge>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>
        </div>
    @else
        {{-- Getting started --}}
        <x-card title="Getting started" subtitle="Set up your cellar in a few steps.">
            <ul class="divide-y divide-border">
                @php
                    $steps = [
                        ['label' => 'Add your suppliers', 'desc' => 'Record who you buy from.', 'icon' => 'users', 'route' => 'suppliers'],
                        ['label' => 'Import a price list', 'desc' => 'Upload a supplier CSV or Excel file to build your catalogue.', 'icon' => 'upload', 'route' => 'import'],
                        ['label' => 'Browse the catalogue', 'desc' => 'Sort, filter and add wines to an order.', 'icon' => 'wine', 'route' => 'catalogue'],
                        ['label' => 'Raise a purchase order', 'desc' => 'Generate a PO PDF and email it to a supplier.', 'icon' => 'clipboard-list', 'route' => 'orders'],
                    ];
                @endphp
                @foreach($steps as $step)
                    @php($exists = \Illuminate\Support\Facades\Route::has($step['route']))
                    <li class="flex items-center gap-4 py-3 first:pt-0 last:pb-0">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                            <x-dynamic-component :component="'icon.'.$step['icon']" class="size-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-foreground">{{ $step['label'] }}</p>
                            <p class="text-sm text-muted-foreground">{{ $step['desc'] }}</p>
                        </div>
                        @if($exists)
                            <x-button :href="route($step['route'])" variant="outline" size="sm" wire:navigate>Open <x-icon.chevron-right class="size-4" /></x-button>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif
</div>
