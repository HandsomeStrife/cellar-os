@use('Domain\Billing\Enums\Plan')
@use('Domain\Billing\Enums\Feature')

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CellarOS · The operating system for the modern wine trade</title>
    <meta name="description" content="Manage your wine catalogue, suppliers, purchase orders and stock in one place. Built for importers, merchants and sommeliers.">
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-background text-foreground antialiased">

    {{-- Header --}}
    <header x-data="{ open: false }" class="sticky top-0 z-50 border-b border-border/70 bg-background/85 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-3.5 sm:px-8">
            <x-app-logo :href="route('home')" />
            <nav class="hidden items-center gap-8 text-sm font-medium text-muted-foreground md:flex">
                <a href="#features" class="transition-colors hover:text-foreground">Features</a>
                <a href="#pricing" class="transition-colors hover:text-foreground">Pricing</a>
                <a href="{{ route('guide') }}" class="transition-colors hover:text-foreground">Guide</a>
            </nav>
            <div class="flex items-center gap-2">
                <x-button :href="route('login')" variant="ghost" size="sm" class="hidden sm:inline-flex">Sign in</x-button>
                <x-button :href="route('register')" size="sm">Get started</x-button>
                <button x-on:click="open = !open" class="text-muted-foreground md:hidden" aria-label="Menu">
                    <x-icon.menu class="size-6" x-show="!open" />
                    <x-icon.x class="size-6" x-show="open" x-cloak />
                </button>
            </div>
        </div>
        <div x-show="open" x-cloak x-transition class="border-t border-border md:hidden">
            <nav class="mx-auto flex max-w-6xl flex-col gap-1 px-5 py-3 text-sm">
                <a href="#features" x-on:click="open=false" class="rounded-md px-2 py-2 hover:bg-accent">Features</a>
                <a href="#pricing" x-on:click="open=false" class="rounded-md px-2 py-2 hover:bg-accent">Pricing</a>
                <a href="{{ route('guide') }}" class="rounded-md px-2 py-2 hover:bg-accent">Guide</a>
                <a href="{{ route('login') }}" class="rounded-md px-2 py-2 hover:bg-accent">Sign in</a>
            </nav>
        </div>
    </header>

    <main id="content">
    {{-- Hero --}}
    <section class="relative isolate overflow-hidden">
        <div class="absolute inset-0 -z-10">
            <video
                class="h-full w-full object-cover"
                autoplay muted loop playsinline preload="metadata"
                aria-hidden="true"
                poster="/media/hero-poster.jpg"
                x-data
                x-init="if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { $el.removeAttribute('autoplay'); $el.pause(); }"
            >
                <source src="/media/hero.webm" type="video/webm">
                <source src="/media/hero.mp4" type="video/mp4">
            </video>
            <div class="absolute inset-0 bg-gradient-to-tr from-[#23090f]/92 via-[#23090f]/78 to-[#23090f]/62"></div>
        </div>

        <div class="mx-auto flex min-h-[86vh] max-w-6xl flex-col justify-center px-5 py-24 sm:px-8">
            <p class="font-mono text-xs uppercase tracking-[0.22em] text-white/80">For importers, merchants &amp; sommeliers</p>
            <h1 class="mt-5 max-w-3xl font-display text-4xl font-semibold leading-[1.05] tracking-tight text-white sm:text-5xl lg:text-6xl">
                The operating system for the modern wine trade.
            </h1>
            <p class="mt-6 max-w-xl text-lg leading-relaxed text-white/85">
                Bring your catalogue, suppliers, purchase orders and stock into one calm, fast workspace. Less spreadsheet wrangling, more selling wine.
            </p>
            <div class="mt-9 flex flex-wrap items-center gap-3">
                <x-button :href="route('register')" size="lg">Start free</x-button>
                <x-button href="#features" variant="inverse" size="lg">
                    See how it works
                    <x-icon.chevron-down class="size-4" />
                </x-button>
            </div>
            <p class="mt-6 text-sm text-white/75">No card required. Free plan to browse and manage suppliers.</p>
        </div>
    </section>

    {{-- Intro --}}
    <section id="features" class="mx-auto max-w-6xl scroll-mt-20 px-5 py-20 sm:px-8 sm:py-28">
        <div class="max-w-2xl">
            <p class="font-mono text-xs uppercase tracking-[0.22em] text-primary">One workspace</p>
            <h2 class="mt-3 font-display text-3xl font-semibold tracking-tight sm:text-4xl">Everything the trade runs on, in one place.</h2>
            <p class="mt-4 text-lg text-muted-foreground">From the first supplier price list to a purchase order on its way, CellarOS keeps the moving parts of a wine business connected, so nothing lives in a spreadsheet you have to remember to update.</p>
        </div>

        <div class="mt-16 space-y-20 sm:mt-20 sm:space-y-28">
            {{-- Feature: Catalogue --}}
            <div class="grid items-center gap-8 lg:grid-cols-2 lg:gap-16">
                <div>
                    <p class="font-mono text-xs uppercase tracking-[0.22em] text-primary">Catalogue</p>
                    <h3 class="mt-3 font-display text-2xl font-semibold tracking-tight sm:text-3xl">Every wine you trade, priced and searchable.</h3>
                    <p class="mt-4 text-muted-foreground">Filter by country, colour or producer, sort by price or vintage, and edit prices inline. Build an order basket as you browse and turn it into purchase orders in one step.</p>
                    <ul class="mt-6 space-y-2.5 text-sm">
                        <li class="flex items-start gap-3"><x-icon.check class="mt-0.5 size-4 shrink-0 text-primary" /><span>Inline price editing with automatic price-per-litre.</span></li>
                        <li class="flex items-start gap-3"><x-icon.check class="mt-0.5 size-4 shrink-0 text-primary" /><span>Shareable, bookmarkable filtered views.</span></li>
                        <li class="flex items-start gap-3"><x-icon.check class="mt-0.5 size-4 shrink-0 text-primary" /><span>Basket groups into one purchase order per supplier.</span></li>
                    </ul>
                </div>
                {{-- Product preview: a mini catalogue, built from real UI --}}
                <div class="overflow-hidden rounded-xl border border-border bg-card shadow-md">
                    <div class="flex items-center gap-2 border-b border-border bg-secondary/50 px-4 py-2.5">
                        <span class="flex gap-1.5">
                            <span class="size-2.5 rounded-full bg-foreground/15"></span>
                            <span class="size-2.5 rounded-full bg-foreground/15"></span>
                            <span class="size-2.5 rounded-full bg-foreground/15"></span>
                        </span>
                        <span class="ml-2 font-mono text-xs text-muted-foreground">cellaros.app/catalogue</span>
                    </div>
                    {{-- Faux toolbar, sells the "filter + basket" claims --}}
                    <div class="flex items-center gap-2 border-b border-border px-4 py-2.5 text-xs">
                        <span class="inline-flex items-center gap-1 rounded-md border border-border px-2 py-1 text-muted-foreground">France <x-icon.chevron-down class="size-3" /></span>
                        <span class="inline-flex items-center gap-1 rounded-md border border-border px-2 py-1 text-muted-foreground">All colours <x-icon.chevron-down class="size-3" /></span>
                        <span class="ml-auto inline-flex items-center gap-1.5 rounded-md bg-primary/10 px-2 py-1 font-medium text-primary">
                            <x-icon.clipboard-list class="size-3.5" /> Basket 3
                        </span>
                    </div>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border text-left font-mono text-[10px] uppercase tracking-wide text-muted-foreground">
                                <th class="px-4 py-2 font-medium">Wine</th>
                                <th class="px-4 py-2 font-medium">Origin</th>
                                <th class="px-4 py-2 text-right font-medium">Price</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @php
                                $previewRows = [
                                    ['Chablis Premier Cru', 'France · Burgundy', '£28.50', '#e9dca6'],
                                    ['Barolo Riserva', 'Italy · Piedmont', '£92.00', '#7b1e3b'],
                                    ['Rioja Gran Reserva', 'Spain · Rioja', '£45.00', '#7b1e3b'],
                                    ['Champagne Brut', 'France · Champagne', '£55.00', '#f0e6c4'],
                                    ['Provence Rosé', 'France · Provence', '£19.50', '#e8a0a8'],
                                ];
                            @endphp
                            @foreach($previewRows as [$wine, $origin, $price, $swatch])
                                <tr>
                                    <td class="px-4 py-2.5">
                                        <span class="flex items-center gap-2 font-medium text-foreground">
                                            <span class="size-2.5 shrink-0 rounded-full ring-1 ring-border" style="background-color: {{ $swatch }}"></span>
                                            {{ $wine }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-muted-foreground">{{ $origin }}</td>
                                    <td class="px-4 py-2.5 text-right">
                                        <div class="font-mono tabular-nums text-foreground">{{ $price }}</div>
                                        @if($loop->first)<div class="font-mono text-[10px] text-muted-foreground">£38.00/L</div>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Feature: Import (image left on desktop, text-first on mobile) --}}
            <div class="grid items-center gap-8 lg:grid-cols-2 lg:gap-16">
                <div>
                    <p class="font-mono text-xs uppercase tracking-[0.22em] text-primary">Imports</p>
                    <h3 class="mt-3 font-display text-2xl font-semibold tracking-tight sm:text-3xl">Drop in a price list, get a clean catalogue.</h3>
                    <p class="mt-4 text-muted-foreground">Upload a supplier's CSV or Excel file and map the columns once. CellarOS standardises grapes and regions, reads prices, vintages and bottle formats out of messy text, and places each wine on the map.</p>
                    <ul class="mt-6 space-y-2.5 text-sm">
                        <li class="flex items-start gap-3"><x-icon.check class="mt-0.5 size-4 shrink-0 text-primary" /><span>Column mappings are remembered per supplier.</span></li>
                        <li class="flex items-start gap-3"><x-icon.check class="mt-0.5 size-4 shrink-0 text-primary" /><span>Re-importing updates wines instead of duplicating.</span></li>
                        <li class="flex items-start gap-3"><x-icon.check class="mt-0.5 size-4 shrink-0 text-primary" /><span>Multi-language colour recognition.</span></li>
                    </ul>
                </div>
                <div class="overflow-hidden rounded-xl border border-border shadow-sm lg:order-first">
                    <img src="/images/warehouse.jpg" alt="Wine bottles in storage" class="aspect-[4/3] w-full object-cover" loading="lazy">
                </div>
            </div>

            {{-- Feature: Orders --}}
            <div class="grid items-center gap-8 lg:grid-cols-2 lg:gap-16">
                <div>
                    <p class="font-mono text-xs uppercase tracking-[0.22em] text-primary">Purchase orders</p>
                    <h3 class="mt-3 font-display text-2xl font-semibold tracking-tight sm:text-3xl">Raise it, send it, receive it.</h3>
                    <p class="mt-4 text-muted-foreground">Create purchase orders from the catalogue or by hand, generate a clean PO PDF, and email it straight to the supplier. When the wine arrives, receiving the order tops up your venue's inventory automatically.</p>
                    <ul class="mt-6 space-y-2.5 text-sm">
                        <li class="flex items-start gap-3"><x-icon.check class="mt-0.5 size-4 shrink-0 text-primary" /><span>Branded PDF purchase orders.</span></li>
                        <li class="flex items-start gap-3"><x-icon.check class="mt-0.5 size-4 shrink-0 text-primary" /><span>Email to the supplier in one click.</span></li>
                        <li class="flex items-start gap-3"><x-icon.check class="mt-0.5 size-4 shrink-0 text-primary" /><span>Receiving flows through to inventory.</span></li>
                    </ul>
                </div>
                {{-- Product preview: a purchase order --}}
                <div class="overflow-hidden rounded-xl border border-border bg-card shadow-md">
                    <div class="flex items-center justify-between border-b border-border px-5 py-3.5">
                        <div>
                            <div class="font-display font-semibold text-foreground">PO #A1F3C9</div>
                            <div class="font-mono text-xs text-muted-foreground">Domaine Laroche</div>
                        </div>
                        <x-badge color="blue">Sent</x-badge>
                    </div>
                    <div class="space-y-2.5 px-5 py-4 text-sm">
                        <div class="flex items-center justify-between border-b border-border pb-2.5">
                            <span class="text-foreground">Chablis Premier Cru <span class="text-muted-foreground">× 12</span></span>
                            <span class="font-mono tabular-nums text-foreground">£342.00</span>
                        </div>
                        <div class="flex items-center justify-between border-b border-border pb-2.5">
                            <span class="text-foreground">Sancerre Les Monts <span class="text-muted-foreground">× 6</span></span>
                            <span class="font-mono tabular-nums text-foreground">£132.00</span>
                        </div>
                        <div class="flex items-center justify-between pt-1">
                            <span class="font-medium text-muted-foreground">Total</span>
                            <span class="font-display text-lg font-semibold tabular-nums text-foreground">£474.00</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 border-t border-border bg-secondary/40 px-5 py-3">
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground">
                            <x-icon.mail class="size-3.5" /> Email to supplier
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-md border border-input px-3 py-1.5 text-xs font-medium text-foreground">
                            <x-icon.download class="size-3.5" /> PDF
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Map / sourcing band (full width image) --}}
    <section class="relative isolate overflow-hidden">
        <img src="/images/vineyard.jpg" alt="Aerial view of a vineyard" class="absolute inset-0 -z-10 h-full w-full object-cover" loading="lazy">
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-[#1c0a11]/85 to-[#1c0a11]/55"></div>
        <div class="mx-auto max-w-6xl px-5 py-24 sm:px-8 sm:py-32">
            <div class="max-w-xl">
                <p class="font-mono text-xs uppercase tracking-[0.22em] text-white/70">Sourcing</p>
                <h2 class="mt-3 font-display text-3xl font-semibold tracking-tight text-white sm:text-4xl">See your range on the map.</h2>
                <p class="mt-4 text-lg text-white/80">Every wine you import is placed by region and country, so your global sourcing reads at a glance, by colour, country and producer.</p>
            </div>
        </div>
    </section>

    {{-- What's included (two-column checklist, not cards) --}}
    <section class="mx-auto max-w-6xl px-5 py-20 sm:px-8 sm:py-28">
        <div class="max-w-2xl">
            <p class="font-mono text-xs uppercase tracking-[0.22em] text-primary">Included</p>
            <h2 class="mt-3 font-display text-3xl font-semibold tracking-tight sm:text-4xl">A complete trade workflow.</h2>
        </div>
        <div class="mt-10 grid gap-x-12 gap-y-3.5 text-sm sm:grid-cols-2">
            @php
                $included = [
                    'Supplier management with saved price-list layouts',
                    'CSV &amp; Excel price-list import with normalisation',
                    'Searchable, filterable wine catalogue',
                    'Inline pricing and price-per-litre',
                    'Purchase orders with PDF and email',
                    'Per-venue inventory with archive &amp; attachments',
                    'Global sourcing map',
                    'Dashboard with stock value and low-stock alerts',
                    'Multi-currency display (GBP, EUR, USD)',
                    'Separate admin back-office',
                ];
            @endphp
            @foreach($included as $item)
                <div class="flex items-start gap-3 border-b border-border/70 pb-3.5 [&:last-child]:border-0 sm:[&:nth-last-child(-n+2)]:border-0">
                    <x-icon.check class="mt-0.5 size-4 shrink-0 text-primary" />
                    <span class="text-foreground">{!! $item !!}</span>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Pricing (comparison table, not a card grid) --}}
    <section id="pricing" class="border-y border-border bg-card/40 scroll-mt-20">
        <div class="mx-auto max-w-6xl px-5 py-20 sm:px-8 sm:py-28">
            <div class="max-w-2xl">
                <p class="font-mono text-xs uppercase tracking-[0.22em] text-primary">Pricing</p>
                <h2 class="mt-3 font-display text-3xl font-semibold tracking-tight sm:text-4xl">Start free. Upgrade as you grow.</h2>
                <p class="mt-4 text-lg text-muted-foreground">Every plan includes the catalogue and supplier management. Paid plans add importing, ordering, inventory and more.</p>
            </div>

            @php($plans = [Plan::Free, ...Plan::paid()])
            @php($featured = \Domain\Billing\Enums\Plan::Pro)
            @php($tint = fn ($plan) => $plan === $featured ? ' bg-primary/[0.06] border-x border-primary/25' : '')
            <div class="mt-12 overflow-x-auto pb-2">
                <table class="w-full min-w-[44rem] border-separate border-spacing-0 text-sm">
                    <thead>
                        <tr>
                            <th scope="col" class="w-1/3 py-4 pr-4 text-left align-bottom"><span class="sr-only">Feature</span></th>
                            @foreach($plans as $plan)
                                <th scope="col" class="rounded-t-lg px-4 py-4 text-left align-bottom{{ $tint($plan) }}{{ $plan === $featured ? ' border-t-2 border-primary' : '' }}">
                                    @if($plan === $featured)
                                        <span class="mb-1.5 inline-block font-mono text-[10px] uppercase tracking-wide text-primary">Most chosen</span>
                                    @endif
                                    <div class="font-display text-lg font-semibold text-foreground">{{ $plan->getLabel() }}</div>
                                    <div class="mt-1 font-mono text-sm text-muted-foreground">{{ $plan->monthlyPrice() }}<span class="text-xs">/mo</span></div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th scope="row" class="border-t border-border py-3 pr-4 text-left font-normal text-foreground">Catalogue &amp; suppliers</th>
                            @foreach($plans as $plan)
                                <td class="border-t border-border px-4 py-3{{ $tint($plan) }}">
                                    <x-icon.check class="size-4 text-primary" /><span class="sr-only">Included</span>
                                </td>
                            @endforeach
                        </tr>
                        @foreach(Feature::cases() as $feature)
                            <tr>
                                <th scope="row" class="border-t border-border py-3 pr-4 text-left font-normal text-foreground">{{ $feature->label() }}</th>
                                @foreach($plans as $plan)
                                    <td class="border-t border-border px-4 py-3{{ $tint($plan) }}">
                                        @if($plan->can($feature))
                                            <x-icon.check class="size-4 text-primary" /><span class="sr-only">Included</span>
                                        @else
                                            <x-icon.x class="size-4 text-muted-foreground/50" /><span class="sr-only">Not included</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                        <tr>
                            <td class="py-5 pr-4"></td>
                            @foreach($plans as $plan)
                                <td class="rounded-b-lg px-4 py-5{{ $tint($plan) }}{{ $plan === $featured ? ' border-b border-primary/25' : '' }}">
                                    <x-button :href="route('register')" :variant="$plan === $featured ? 'primary' : 'outline'" size="md">
                                        {{ $plan === Plan::Free ? 'Start free' : 'Choose '.$plan->getLabel() }}
                                    </x-button>
                                </td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- Final CTA --}}
    <section class="relative isolate overflow-hidden">
        <img src="/images/pour.jpg" alt="Pouring a glass of wine" class="absolute inset-0 -z-10 h-full w-full object-cover" loading="lazy">
        <div class="absolute inset-0 -z-10 bg-[#1c0a11]/80"></div>
        <div class="mx-auto max-w-6xl px-5 py-24 text-center sm:px-8 sm:py-32">
            <h2 class="mx-auto max-w-2xl font-display text-3xl font-semibold tracking-tight text-white sm:text-5xl">Put your wine business on solid ground.</h2>
            <p class="mx-auto mt-5 max-w-xl text-lg text-white/80">Create an account in under a minute and import your first price list today.</p>
            <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                <x-button :href="route('register')" size="lg">Start free</x-button>
                <x-button :href="route('guide')" variant="inverse" size="lg">Read the guide</x-button>
            </div>
        </div>
    </section>
    </main>

    {{-- Footer --}}
    <footer class="border-t border-border">
        <div class="mx-auto flex max-w-6xl flex-col gap-6 px-5 py-10 sm:flex-row sm:items-center sm:justify-between sm:px-8">
            <x-app-logo :href="route('home')" />
            <nav class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-muted-foreground">
                <a href="#features" class="hover:text-foreground">Features</a>
                <a href="#pricing" class="hover:text-foreground">Pricing</a>
                <a href="{{ route('guide') }}" class="hover:text-foreground">Guide</a>
                <a href="{{ route('login') }}" class="hover:text-foreground">Sign in</a>
            </nav>
            <p class="text-sm text-muted-foreground">&copy; {{ date('Y') }} CellarOS</p>
        </div>
    </footer>

</body>
</html>
