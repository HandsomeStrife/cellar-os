<div class="space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="font-serif text-2xl font-semibold">
                Welcome{{ $user?->full_name ? ', '.\Illuminate\Support\Str::of($user->full_name)->before(' ') : '' }}
            </h2>
            <p class="mt-1 text-sm text-muted-foreground">Your cellar at a glance.</p>
        </div>
        @if($plan)
            <x-badge color="wine">{{ $plan->getLabel() }} plan</x-badge>
        @endif
    </div>

    {{-- KPI stats --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <x-stat label="Wines in catalogue" :value="number_format($productCount)" icon="wine" />
        <x-stat label="Suppliers" :value="number_format($supplierCount)" icon="users" />
        <x-stat label="Open orders" :value="number_format($openOrderCount)" icon="clipboard-list" />
    </div>

    {{-- Getting started --}}
    <x-card title="Getting started" subtitle="Set up your cellar in a few steps.">
        <ul class="divide-y divide-border">
            @php
                $steps = [
                    ['n' => 1, 'label' => 'Add your suppliers', 'desc' => 'Record who you buy from.', 'icon' => 'users', 'route' => 'suppliers'],
                    ['n' => 2, 'label' => 'Import a price list', 'desc' => 'Upload a supplier CSV or Excel file to build your catalogue.', 'icon' => 'upload', 'route' => 'import'],
                    ['n' => 3, 'label' => 'Browse the catalogue', 'desc' => 'Sort, filter and add wines to an order.', 'icon' => 'wine', 'route' => 'catalogue'],
                    ['n' => 4, 'label' => 'Raise a purchase order', 'desc' => 'Generate a PO PDF and email it to a supplier.', 'icon' => 'clipboard-list', 'route' => 'orders'],
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
                        <x-button :href="route($step['route'])" variant="outline" size="sm" wire:navigate>
                            Open
                            <x-icon.chevron-right class="size-4" />
                        </x-button>
                    @else
                        <x-badge color="gray">Soon</x-badge>
                    @endif
                </li>
            @endforeach
        </ul>
    </x-card>
</div>
