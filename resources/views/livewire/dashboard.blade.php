@use('Domain\Shared\Support\Currency')
@use('Illuminate\Support\Str')

<div class="space-y-10">
    {{-- Masthead --}}
    <header class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-2 border-b border-border pb-5">
        <div>
            <p class="font-mono text-xs uppercase tracking-[0.2em] text-muted-foreground">Cellar overview</p>
            <h2 class="mt-1.5 font-serif text-3xl font-semibold tracking-tight">
                {{ $user?->full_name ? Str::before($user->full_name, ' ').'’s cellar' : 'Your cellar' }}
            </h2>
        </div>
        @if($plan)
            <p class="font-mono text-xs uppercase tracking-wider text-muted-foreground">{{ $plan->getLabel() }} plan</p>
        @endif
    </header>

    @if($inventoryBottles > 0)
        {{-- Headline figures: grouped by type, alignment and a single rule — never boxed --}}
        <section class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="font-mono text-xs uppercase tracking-[0.18em] text-muted-foreground">In-stock value</p>
                <p class="mt-1 font-serif text-5xl font-semibold tabular-nums tracking-tight sm:text-6xl">{{ Currency::format($inventoryValue, $currency) }}</p>
            </div>
            <dl class="flex flex-wrap gap-x-8 gap-y-4">
                @foreach([
                    ['bottles', number_format($inventoryBottles)],
                    ['distinct wines', number_format($inventoryLabels)],
                    ['my suppliers', number_format($activeSuppliers)],
                    ['wines to browse', number_format($productCount)],
                ] as [$label, $val])
                    <div class="border-l border-border pl-4">
                        <dd class="font-mono text-2xl font-medium tabular-nums">{{ $val }}</dd>
                        <dt class="mt-1 text-xs uppercase tracking-wider text-muted-foreground">{{ $label }}</dt>
                    </div>
                @endforeach
            </dl>
        </section>

        {{-- Signature: the cellar composition, drawn in the wines' own colours --}}
        <section>
            <div class="flex items-baseline justify-between">
                <h3 class="font-serif text-lg font-semibold">Cellar composition</h3>
                <span class="font-mono text-xs tabular-nums text-muted-foreground">{{ number_format($compositionTotal) }} bottles</span>
            </div>
            <div class="mt-3 flex h-4 w-full overflow-hidden rounded-full ring-1 ring-border">
                @foreach($byColour as $colour => $qty)
                    <div
                        class="h-full border-r border-background/60 last:border-r-0"
                        style="width: {{ $compositionTotal > 0 ? round($qty / $compositionTotal * 100, 2) : 0 }}%; background-color: {{ $colourSwatch($colour) }}"
                        title="{{ $colour }} — {{ number_format($qty) }} bottles"
                    ></div>
                @endforeach
            </div>
            <ul class="mt-3 flex flex-wrap gap-x-6 gap-y-1.5">
                @foreach($byColour as $colour => $qty)
                    <li class="inline-flex items-center gap-2 text-sm {{ $colour === 'Unknown' ? 'text-muted-foreground' : '' }}">
                        <span class="size-2.5 rounded-full ring-1 ring-border dark:ring-white/30" style="background-color: {{ $colourSwatch($colour) }}"></span>
                        <span>{{ $colour === 'Unknown' ? 'Uncategorised' : $colour }}</span>
                        <span class="font-mono text-xs tabular-nums text-muted-foreground">{{ $compositionTotal > 0 ? round($qty / $compositionTotal * 100) : 0 }}%</span>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- What needs you, and what just happened --}}
        {{-- min-w-0 on the grid children: without it a long nowrap supplier
             name sets the track's min-content and overflows small screens. --}}
        <div class="grid gap-x-12 gap-y-10 lg:grid-cols-2">
            <section class="min-w-0">
                <h3 class="flex items-baseline gap-3 font-serif text-lg font-semibold">
                    Needs attention
                    {{-- Only counts that are non-zero earn a mention. --}}
                    @if($outOfStockCount + $lowStockCount > 0)
                        <span class="font-mono text-xs font-normal uppercase tracking-wider text-primary">
                            {{ collect([$outOfStockCount > 0 ? "{$outOfStockCount} out" : null, $lowStockCount > 0 ? "{$lowStockCount} low" : null])->filter()->implode(' · ') }}
                        </span>
                    @endif
                </h3>
                @if($lowStockItems === [] && $outOfStockCount === 0)
                    <p class="mt-4 text-sm text-muted-foreground">Everything’s well stocked.</p>
                @else
                    <ul class="mt-3 divide-y divide-border">
                        @foreach($lowStockItems as $item)
                            <li class="flex items-center justify-between gap-3 py-2.5">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ $item['name'] }}</p>
                                    @if($item['producer'])<p class="truncate text-xs text-muted-foreground">{{ $item['producer'] }}</p>@endif
                                </div>
                                <span class="shrink-0 font-mono text-sm tabular-nums {{ $item['qty'] <= 3 ? 'text-primary' : 'text-muted-foreground' }}">{{ $item['qty'] }} left</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="min-w-0">
                <div class="flex items-baseline justify-between">
                    <h3 class="font-serif text-lg font-semibold">Recent orders</h3>
                    <a href="{{ route('orders') }}" wire:navigate class="text-sm text-primary hover:underline">View all</a>
                </div>
                @forelse($recentOrders as $order)
                    <div wire:key="recent-{{ $order['id'] }}" class="flex items-center justify-between gap-3 border-b border-border py-2.5 last:border-0">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">{{ $order['supplier'] }}</p>
                            <p class="text-xs text-muted-foreground">{{ $order['items'] }} {{ Str::plural('line', $order['items']) }} · {{ $order['created_at']?->format('j M Y') }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <span class="font-mono text-sm tabular-nums">{{ Currency::format($order['total'], $currency) }}</span>
                            <x-badge :color="$order['status']->getColour()">{{ $order['status']->getLabel() }}</x-badge>
                        </div>
                    </div>
                @empty
                    <p class="mt-4 text-sm text-muted-foreground">No orders yet.</p>
                @endforelse
                @if($openOrderCount > 0)
                    <a href="{{ route('orders') }}" wire:navigate class="mt-4 inline-flex items-center gap-1 text-sm text-primary hover:underline">
                        {{ $openOrderCount }} open {{ Str::plural('order', $openOrderCount) }} to follow up <x-icon.chevron-right class="size-4" />
                    </a>
                @endif
            </section>
        </div>

        {{-- Provenance: where the cellar comes from, as ranked indices --}}
        @if($topRegions !== [] || $byCountry !== [])
            <div class="grid gap-x-12 gap-y-10 border-t border-border pt-8 lg:grid-cols-2">
                @if($topRegions !== [])
                    @php($maxRegion = max($topRegions))
                    <section>
                        <h3 class="font-serif text-lg font-semibold">Top regions</h3>
                        <ol class="mt-4 space-y-2.5">
                            @foreach($topRegions as $region => $qty)
                                <li class="flex items-center gap-3 text-sm">
                                    <span class="w-5 shrink-0 font-mono text-xs tabular-nums text-muted-foreground">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                    <span class="w-32 shrink-0 truncate">{{ $region }}</span>
                                    <span class="relative h-px flex-1 bg-border">
                                        <span class="absolute -top-px left-0 h-[3px] rounded-full bg-primary/60" style="width: {{ $maxRegion > 0 ? round($qty / $maxRegion * 100) : 0 }}%"></span>
                                    </span>
                                    <span class="shrink-0 font-mono text-xs tabular-nums text-muted-foreground">{{ number_format($qty) }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </section>
                @endif

                @if($byCountry !== [])
                    <section>
                        <h3 class="font-serif text-lg font-semibold">By country</h3>
                        <ol class="mt-4 space-y-2.5">
                            @foreach($byCountry as $country => $data)
                                <li class="flex items-baseline justify-between gap-3 text-sm">
                                    <span class="flex items-baseline gap-3 truncate">
                                        <span class="w-5 shrink-0 font-mono text-xs tabular-nums text-muted-foreground">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                        <span class="truncate">{{ $country }}</span>
                                    </span>
                                    <span class="shrink-0 font-mono text-xs tabular-nums text-muted-foreground">
                                        {{ number_format($data['count']) }} btl · {{ Currency::format($data['value'], $currency) }}
                                    </span>
                                </li>
                            @endforeach
                        </ol>
                    </section>
                @endif
            </div>
        @endif
    @else
        {{-- Getting started: a real four-step sequence — numbering carries the order --}}
        <section>
            <h3 class="font-serif text-xl font-semibold">Set up your cellar</h3>
            <p class="mt-1 text-sm text-muted-foreground">Four steps to a working catalogue and your first order.</p>
            <ol class="mt-6 divide-y divide-border border-y border-border">
                @php($steps = [
                    ['label' => 'Add your suppliers', 'desc' => 'Record who you buy from.', 'route' => 'suppliers'],
                    ['label' => 'Import a price list', 'desc' => 'Upload a supplier CSV or Excel file to build your catalogue.', 'route' => 'import'],
                    ['label' => 'Browse the catalogue', 'desc' => 'Sort, filter and add wines to an order.', 'route' => 'catalogue'],
                    ['label' => 'Raise a purchase order', 'desc' => 'Generate a PO PDF and email it to a supplier.', 'route' => 'orders'],
                ])
                @foreach($steps as $step)
                    <li class="flex items-center gap-5 py-4">
                        <span class="font-mono text-sm tabular-nums text-primary">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-foreground">{{ $step['label'] }}</p>
                            <p class="text-sm text-muted-foreground">{{ $step['desc'] }}</p>
                        </div>
                        @if(\Illuminate\Support\Facades\Route::has($step['route']))
                            <x-button :href="route($step['route'])" variant="outline" size="sm" wire:navigate>Open <x-icon.chevron-right class="size-4" /></x-button>
                        @endif
                    </li>
                @endforeach
            </ol>
        </section>
    @endif
</div>
