@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.', CellarOS' : 'CellarOS' }}</title>
    <link rel="icon" type="image/svg+xml" href="/cellar-os-logo.svg">

    {{-- Auth screens are intentionally always light, matching the marketing site. --}}
    <script>document.documentElement.classList.remove('dark');</script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-background text-foreground antialiased">
    <div class="flex min-h-screen">
        {{-- Brand panel: full-height image with a claret scrim, desktop only --}}
        <div class="relative hidden w-1/2 shrink-0 overflow-hidden lg:block xl:w-[55%]">
            <img src="/images/cellar.jpg" alt="" class="absolute inset-0 h-full w-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-b from-[#1c0a11]/55 via-[#1c0a11]/40 to-[#1c0a11]/85"></div>
            <div class="relative flex h-full flex-col justify-between p-10 text-white xl:p-14 [&_*]:[text-shadow:0_1px_14px_rgba(0,0,0,0.45)]">
                <x-app-logo :href="route('home')" mark-class="" />
                <div>
                    <p class="font-mono text-xs uppercase tracking-[0.22em] text-white/80">For importers, merchants &amp; sommeliers</p>
                    <h2 class="mt-4 max-w-md font-display text-3xl font-semibold leading-tight tracking-tight xl:text-4xl">
                        The operating system for the modern wine trade.
                    </h2>
                    <p class="mt-4 max-w-sm text-lg text-white/80">
                        Catalogue, suppliers, purchase orders and stock in one calm, fast workspace.
                    </p>
                </div>
            </div>
        </div>

        {{-- Form area --}}
        <div class="flex flex-1 flex-col px-5 py-10 sm:px-8">
            <div class="flex justify-center lg:hidden">
                <x-app-logo :href="route('home')" />
            </div>

            <div class="flex flex-1 items-center justify-center py-10">
                <div class="w-full max-w-md">
                    {{ $slot }}
                </div>
            </div>

            <p class="text-center text-xs text-muted-foreground">
                &copy; {{ date('Y') }} CellarOS Ltd. The operating system for the modern wine trade.
            </p>
        </div>
    </div>
</body>
</html>
