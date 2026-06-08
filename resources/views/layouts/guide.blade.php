@props(['title' => null])

@php
    use App\Livewire\Guide;

    $sections = Guide::sections();
    $requestedSlug = request()->route('section') ?? 'welcome';
    $knownSlugs = collect($sections)->flatMap(fn ($g) => array_keys($g['items']))->all();
    $activeSlug = in_array($requestedSlug, $knownSlugs, true) ? $requestedSlug : 'welcome';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Guide' }} — CellarOS</title>
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-background text-foreground antialiased">
    <div class="flex min-h-screen flex-col">
        {{-- Header --}}
        <header class="border-b border-border">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-6 py-4">
                <div class="flex items-center gap-3">
                    <x-app-logo :href="route('home')" />
                    <span class="hidden font-mono text-[10px] uppercase tracking-[0.25em] text-muted-foreground sm:inline">Guide</span>
                </div>
                <nav class="flex items-center gap-2 text-sm">
                    @auth
                        <x-button :href="route('dashboard')" variant="ghost" size="sm">Dashboard</x-button>
                    @else
                        <x-button :href="route('login')" variant="ghost" size="sm">Sign in</x-button>
                        <x-button :href="route('register')" size="sm">Get started</x-button>
                    @endauth
                </nav>
            </div>
        </header>

        {{-- Docs body: sidebar + main --}}
        <div class="mx-auto flex w-full max-w-7xl flex-1 gap-10 px-6">
            <aside class="hidden w-64 shrink-0 md:block">
                <nav class="sticky top-6 max-h-[calc(100vh-3rem)] overflow-y-auto py-10 pr-4" aria-label="Guide sections">
                    @foreach($sections as $group)
                        <div class="mb-6">
                            <p class="mb-2 px-2 font-mono text-[10px] font-medium uppercase tracking-[0.18em] text-muted-foreground">{{ $group['title'] }}</p>
                            <ul class="space-y-px">
                                @foreach($group['items'] as $slug => $entry)
                                    @php($isActive = $slug === $activeSlug)
                                    <li>
                                        <a
                                            href="{{ url('/guide/'.$slug) }}"
                                            wire:navigate
                                            @class([
                                                'block rounded-md border-l-2 px-3 py-1.5 text-[13px] transition-colors',
                                                'border-primary bg-primary/[0.07] text-primary font-medium' => $isActive,
                                                'border-transparent text-muted-foreground hover:border-border hover:text-foreground' => ! $isActive,
                                            ])
                                            @if($isActive) aria-current="page" @endif
                                        >{{ $entry['title'] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </nav>
            </aside>

            <main class="min-w-0 flex-1 py-10 md:py-14">
                {{ $slot }}
            </main>
        </div>

        <footer class="mt-10 border-t border-border">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-6 py-5 text-xs text-muted-foreground">
                <span>&copy; {{ date('Y') }} CellarOS</span>
                <span>The operating system for the modern wine trade.</span>
            </div>
        </footer>
    </div>
</body>
</html>
