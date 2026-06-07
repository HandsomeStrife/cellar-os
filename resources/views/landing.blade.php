<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CellarOS — the operating system for the modern wine trade</title>

    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-background text-foreground antialiased">
    <header class="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
        <x-app-logo :href="route('home')" />
        <nav class="flex items-center gap-2">
            <x-button :href="route('login')" variant="ghost" size="md">Sign in</x-button>
            <x-button :href="route('register')" variant="primary" size="md">Get started</x-button>
        </nav>
    </header>

    <main>
        {{-- Hero --}}
        <section class="mx-auto max-w-6xl px-6 pb-16 pt-12 sm:pt-20">
            <div class="mx-auto max-w-3xl text-center">
                <span class="inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-3 py-1 text-xs font-medium text-muted-foreground">
                    <x-icon.grape class="size-4 text-primary" />
                    For importers, merchants &amp; sommeliers
                </span>
                <h1 class="mt-6 font-serif text-4xl font-semibold leading-tight tracking-tight sm:text-6xl">
                    The operating system for the<br class="hidden sm:block"> modern <span class="text-primary">wine trade</span>.
                </h1>
                <p class="mx-auto mt-6 max-w-2xl text-lg text-muted-foreground">
                    Manage inventory, suppliers and purchase orders, import supplier price lists in seconds,
                    and see your sourcing on a global map — all in one place.
                </p>
                <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                    <x-button :href="route('register')" variant="primary" size="lg">Start free</x-button>
                    <x-button :href="route('login')" variant="outline" size="lg">Sign in</x-button>
                </div>
            </div>
        </section>

        {{-- Features --}}
        <section class="border-t border-border bg-card/40">
            <div class="mx-auto grid max-w-6xl gap-px overflow-hidden px-6 py-16 sm:grid-cols-2 lg:grid-cols-3">
                @php
                    $features = [
                        ['icon' => 'wine', 'title' => 'Catalogue', 'desc' => 'A filterable, sortable catalogue of every wine you trade, with inline pricing.'],
                        ['icon' => 'users', 'title' => 'Suppliers', 'desc' => 'Keep supplier contacts, terms and price-list mappings in one tidy place.'],
                        ['icon' => 'upload', 'title' => 'Smart imports', 'desc' => 'Drop in a CSV or Excel price list and map columns once — we normalise the rest.'],
                        ['icon' => 'clipboard-list', 'title' => 'Purchase orders', 'desc' => 'Build orders, generate PDFs and email them straight to your suppliers.'],
                        ['icon' => 'package', 'title' => 'Inventory', 'desc' => 'Track received stock per venue, with attachments for invoices and tasting notes.'],
                        ['icon' => 'map', 'title' => 'Global sourcing map', 'desc' => 'Visualise where your wines come from, by country and region.'],
                    ];
                @endphp
                @foreach($features as $f)
                    <div class="bg-card p-6">
                        <span class="flex size-10 items-center justify-center rounded-md bg-primary/10 text-primary">
                            <x-dynamic-component :component="'icon.'.$f['icon']" class="size-5" />
                        </span>
                        <h3 class="mt-4 font-serif text-lg font-semibold">{{ $f['title'] }}</h3>
                        <p class="mt-1.5 text-sm text-muted-foreground">{{ $f['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    </main>

    <footer class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-6 py-8 text-sm text-muted-foreground sm:flex-row">
        <x-app-logo :show-text="true" class="opacity-80" />
        <p>&copy; {{ date('Y') }} CellarOS. The operating system for the modern wine trade.</p>
    </footer>
</body>
</html>
