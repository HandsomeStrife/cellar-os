@php
    $modules = [
        [
            'icon' => 'layout-dashboard', 'title' => 'Dashboard', 'route' => 'dashboard', 'plan' => null,
            'what' => 'Your cellar at a glance: catalogue size, bottles & value in stock, active suppliers, open orders, and low/out-of-stock counts, plus inventory breakdowns by colour and country, recent orders and low-stock alerts.',
            'journey' => ['Sign in to land on the dashboard.', 'Review the KPI cards and breakdowns.', 'Jump into any area from the cards or the sidebar.'],
        ],
        [
            'icon' => 'users', 'title' => 'Suppliers', 'route' => 'suppliers', 'plan' => null,
            'what' => 'Maintain the merchants and importers you buy from — name, contact, email, phone, location and active/inactive status.',
            'journey' => ['Open Suppliers.', 'Click “New supplier” and fill the form.', 'Edit a card to update details, or click the status badge to toggle Active/Inactive.', 'Delete suppliers you no longer use.'],
        ],
        [
            'icon' => 'upload', 'title' => 'Import', 'route' => 'import', 'plan' => 'Starter',
            'what' => 'Turn a supplier price list (CSV or Excel) into catalogue wines. A 4-step wizard maps your columns, previews the normalised result and imports — standardising grapes/regions, parsing prices/vintages/formats, and geocoding for the map. Re-importing updates existing wines instead of duplicating.',
            'journey' => ['Open Import and choose a supplier.', 'Upload a .csv/.xls/.xlsx file.', 'Confirm the auto-mapped columns (the wine name is required).', 'Preview the first rows, then import. The mapping is remembered for next time.'],
        ],
        [
            'icon' => 'wine', 'title' => 'Catalogue', 'route' => 'catalogue', 'plan' => null,
            'what' => 'Browse, search, filter (country, colour) and sort every wine you trade. Edit unit prices inline, delete wines, and add them to a basket.',
            'journey' => ['Open Catalogue and filter/sort to find wines.', 'Click a price to edit it inline.', 'Click “+” to add wines to the basket.', 'Open the basket and “Create purchase orders” — one draft PO per supplier.'],
        ],
        [
            'icon' => 'clipboard-list', 'title' => 'Orders', 'route' => 'orders', 'plan' => 'Starter',
            'what' => 'Manage purchase orders through their lifecycle (Draft → Sent → Received…). Build orders from the basket or manually, download a PO PDF, email it to the supplier, and receive a sent order to push its stock into inventory.',
            'journey' => ['Open Orders (or arrive from the catalogue basket).', 'Create an order: pick a supplier/venue and add lines.', 'Download the PDF or email it to the supplier (marks it Sent).', 'When stock arrives, “Receive” it into the order’s venue inventory.'],
        ],
        [
            'icon' => 'package', 'title' => 'Inventory', 'route' => 'inventory', 'plan' => 'Starter',
            'what' => 'Track received stock per venue. Adjust quantities, archive/restore lines, and attach invoices or tasting notes. Manual entry, archiving and attachments are Pro features; a second+ venue is a Group feature.',
            'journey' => ['Open Inventory and choose (or create) a venue.', 'Receive stock via an order, or add it manually (Pro).', 'Adjust quantities with the steppers.', 'Attach files (Pro), and archive lines you no longer stock (Pro).'],
        ],
        [
            'icon' => 'map', 'title' => 'Sourcing map', 'route' => 'map', 'plan' => null,
            'what' => 'See where your wines come from on a world map, with a by-country breakdown. Wines are placed using coordinates added during import.',
            'journey' => ['Import or add wines with a country/region.', 'Open the Map to see them plotted.', 'Click a marker for the wine’s details.'],
        ],
        [
            'icon' => 'credit-card', 'title' => 'Pricing & billing', 'route' => 'pricing', 'plan' => null,
            'what' => 'Compare plans and upgrade. Each plan unlocks more features (see the matrix below). Manage your subscription from the billing portal.',
            'journey' => ['Open Pricing.', 'Choose a plan to upgrade (or switch).', 'Manage or cancel anytime from “Manage billing”.'],
        ],
    ];
@endphp

<div class="mx-auto max-w-4xl space-y-8">
    <div>
        <h2 class="font-serif text-3xl font-semibold">CellarOS guide</h2>
        <p class="mt-2 text-muted-foreground">Everything CellarOS does, and how to get things done. The operating system for the modern wine trade.</p>
    </div>

    {{-- Quick start --}}
    <x-card title="Quick start" subtitle="From zero to your first order.">
        <ol class="ml-5 list-decimal space-y-1.5 text-sm">
            <li><span class="font-medium">Add suppliers</span> — record who you buy from.</li>
            <li><span class="font-medium">Build your catalogue</span> — import a supplier price list (Starter+), or add wines as you go.</li>
            <li><span class="font-medium">Create a purchase order</span> — add wines to the basket and turn it into draft POs grouped by supplier.</li>
            <li><span class="font-medium">Send &amp; receive</span> — email the PO to the supplier, then receive it to stock your venue’s inventory.</li>
            <li><span class="font-medium">Track</span> — watch stock, value and sourcing on the dashboard and map.</li>
        </ol>
    </x-card>

    {{-- Modules --}}
    <div class="space-y-4">
        <h3 class="font-serif text-xl font-semibold">Features</h3>
        @foreach($modules as $m)
            <x-card>
                <div class="flex items-start gap-4">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                        <x-dynamic-component :component="'icon.'.$m['icon']" class="size-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h4 class="font-serif text-lg font-semibold">{{ $m['title'] }}</h4>
                            @if($m['plan'])<x-badge color="wine">{{ $m['plan'] }}+</x-badge>@endif
                            @if(\Illuminate\Support\Facades\Route::has($m['route']))
                                <a href="{{ route($m['route']) }}" class="text-sm text-primary hover:underline" wire:navigate>Open →</a>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">{{ $m['what'] }}</p>
                        <div class="mt-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">User journey</p>
                            <ol class="mt-1 ml-5 list-decimal space-y-0.5 text-sm">
                                @foreach($m['journey'] as $step)<li>{{ $step }}</li>@endforeach
                            </ol>
                        </div>
                    </div>
                </div>
            </x-card>
        @endforeach
    </div>

    {{-- Plan matrix --}}
    <div class="space-y-3">
        <h3 class="font-serif text-xl font-semibold">What each plan unlocks</h3>
        <div class="overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
            <table class="w-full text-sm">
                <thead class="border-b border-border bg-secondary/40">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Feature</th>
                        @foreach($plans as $plan)
                            <th class="px-3 py-2 text-center text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ $plan->getLabel() }}<br><span class="font-normal normal-case">{{ $plan->monthlyPrice() }}/mo</span></th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr>
                        <td class="px-3 py-2">Catalogue &amp; suppliers</td>
                        @foreach($plans as $plan)<td class="px-3 py-2 text-center text-primary"><x-icon.check class="mx-auto size-4" /></td>@endforeach
                    </tr>
                    @foreach($features as $feature)
                        <tr>
                            <td class="px-3 py-2">{{ $feature->label() }}</td>
                            @foreach($plans as $plan)
                                <td class="px-3 py-2 text-center">
                                    @if($plan->can($feature))
                                        <x-icon.check class="mx-auto size-4 text-primary" />
                                    @else
                                        <span class="text-muted-foreground/40">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-muted-foreground">Administrators manage users and plans from the separate <span class="font-medium">/admin</span> back-office.</p>
    </div>
</div>
