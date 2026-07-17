@props(['title' => null])

@php
    $user = auth()->user();

    // Sidebar IA, grouped by purpose: the daily trade work, then account, then
    // help. Items whose route doesn't exist yet render inert ("soon").
    $navGroups = [
        ['heading' => 'Trade', 'items' => [
            ['label' => 'Dashboard', 'icon' => 'layout-dashboard', 'route' => 'dashboard'],
            ['label' => 'Catalogue', 'icon' => 'wine', 'route' => 'catalogue'],
            ['label' => 'Suppliers', 'icon' => 'users', 'route' => 'suppliers'],
            ['label' => 'Inventory', 'icon' => 'package', 'route' => 'inventory'],
            ['label' => 'Orders', 'icon' => 'clipboard-list', 'route' => 'orders'],
            ['label' => 'Import', 'icon' => 'upload', 'route' => 'import'],
            ['label' => 'Map', 'icon' => 'map', 'route' => 'map'],
        ]],
        ['heading' => 'Account', 'items' => array_values(array_filter([
            // Team management is for owners/managers only.
            $user?->role?->canManageTeam() ? ['label' => 'Team', 'icon' => 'user', 'route' => 'team'] : null,
            ['label' => 'Pricing', 'icon' => 'credit-card', 'route' => 'pricing'],
        ]))],
        ['heading' => 'Help', 'items' => [
            ['label' => 'Guide', 'icon' => 'file-text', 'route' => 'guide'],
        ]],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · CellarOS' : 'CellarOS' }}</title>
    <link rel="icon" type="image/svg+xml" href="/cellar-os-logo.svg">

    <script>
        // Apply the theme before paint, and re-apply after every wire:navigate
        // (SPA navigation does not re-run head scripts, which dropped the class).
        (function () {
            const apply = () => {
                // Light by default; dark only when the user explicitly chose it.
                const dark = localStorage.theme === 'dark';
                document.documentElement.classList.toggle('dark', dark);
            };
            apply();
            document.addEventListener('livewire:navigated', apply);
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-background text-foreground antialiased">
    <x-impersonation-banner />
    <div x-data="{ sidebarOpen: false }" x-on:keydown.escape.window="sidebarOpen = false" class="flex min-h-screen">
        {{-- Mobile backdrop --}}
        <div
            x-show="sidebarOpen"
            x-cloak
            x-on:click="sidebarOpen = false"
            class="fixed inset-0 z-30 bg-black/50 lg:hidden"
            aria-hidden="true"
        ></div>

        {{-- Sidebar --}}
        <aside
            x-bind:class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
            class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-sidebar text-sidebar-foreground transition-transform lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
        >
            <div class="flex h-16 items-center justify-between border-b border-sidebar-border px-5">
                <a href="{{ route('dashboard') }}" wire:navigate class="inline-flex items-center gap-2.5">
                    <x-icon.logo class="size-7 shrink-0 text-sidebar-primary" />
                    <span class="font-serif text-lg font-semibold tracking-tight">Cellar<span class="text-sidebar-primary">OS</span></span>
                </a>
                <button x-on:click="sidebarOpen = false" aria-label="Close menu" class="-m-2 flex size-9 items-center justify-center rounded-md text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-foreground lg:hidden">
                    <x-icon.x class="size-5" />
                </button>
            </div>

            <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5" aria-label="Primary">
                @foreach($navGroups as $group)
                    @continue($group['items'] === [])
                    <div>
                        <p class="px-3 pb-2 font-mono text-[0.65rem] uppercase tracking-[0.18em] text-sidebar-foreground/45">{{ $group['heading'] }}</p>
                        <div class="space-y-0.5">
                            @foreach($group['items'] as $item)
                                @php($active = request()->routeIs($item['route'].'*'))
                                @if(\Illuminate\Support\Facades\Route::has($item['route']))
                                    <a
                                        href="{{ route($item['route']) }}"
                                        wire:navigate
                                        @class([
                                            'group relative flex items-center gap-3 rounded-md px-3 py-2 text-sm transition',
                                            'bg-sidebar-accent font-semibold text-sidebar-accent-foreground' => $active,
                                            'font-medium text-sidebar-foreground/70 hover:bg-sidebar-accent/60 hover:text-sidebar-foreground' => ! $active,
                                        ])
                                        @if($active) aria-current="page" @endif
                                    >
                                        @if($active)
                                            <span class="absolute inset-y-1.5 left-0 w-0.5 rounded-full bg-sidebar-primary"></span>
                                        @endif
                                        <x-dynamic-component
                                            :component="'icon.'.$item['icon']"
                                            @class([
                                                'size-5 shrink-0 transition',
                                                'text-sidebar-primary' => $active,
                                                'text-sidebar-foreground/55 group-hover:text-sidebar-foreground' => ! $active,
                                            ])
                                        />
                                        {{ $item['label'] }}
                                    </a>
                                @else
                                    <span class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-sidebar-foreground/35" aria-disabled="true">
                                        <x-dynamic-component :component="'icon.'.$item['icon']" class="size-5 shrink-0" />
                                        {{ $item['label'] }}
                                        <span class="ml-auto font-mono text-[0.6rem] uppercase tracking-wider">soon</span>
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>
        </aside>

        {{-- Main column --}}
        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-20 flex h-16 items-center gap-4 border-b border-border bg-background px-4 sm:px-6">
                <button x-on:click="sidebarOpen = true" aria-label="Open menu" class="-ml-2 flex size-10 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground lg:hidden">
                    <x-icon.menu class="size-6" />
                </button>

                <div class="min-w-0 flex-1">
                    @if($title)
                        <h1 class="truncate font-serif text-lg font-semibold">{{ $title }}</h1>
                    @endif
                </div>

                {{-- Theme toggle --}}
                <button
                    x-on:click="
                        document.documentElement.classList.toggle('dark');
                        localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
                    "
                    class="flex size-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-accent hover:text-foreground"
                    aria-label="Toggle theme"
                >
                    <x-icon.moon class="size-5 dark:hidden" />
                    <x-icon.sun class="hidden size-5 dark:block" />
                </button>

                {{-- User menu (or sign-in for guests, e.g. on the public guide) --}}
                @guest
                    <div class="flex items-center gap-2">
                        <x-button :href="route('login')" variant="ghost" size="sm">Sign in</x-button>
                        <x-button :href="route('home').'#contact'" size="sm">Enquire</x-button>
                    </div>
                @else
                <div x-data="{ open: false }" x-on:keydown.escape="open = false" class="relative">
                    <button x-on:click="open = ! open" aria-label="Account menu" aria-haspopup="menu" x-bind:aria-expanded="open" class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm transition hover:bg-accent">
                        <span class="flex size-8 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <x-icon.user class="size-4" />
                        </span>
                        <span class="hidden max-w-[10rem] truncate font-medium sm:block">{{ $user?->full_name ?? $user?->email }}</span>
                        <x-icon.chevron-down class="size-4 text-muted-foreground" />
                    </button>

                    <div
                        x-show="open"
                        x-cloak
                        x-transition
                        x-on:click.outside="open = false"
                        class="absolute right-0 mt-2 w-56 rounded-md border border-border bg-popover p-1 text-popover-foreground shadow-lg"
                    >
                        <div class="border-b border-border px-3 py-2">
                            <p class="truncate text-sm font-medium">{{ $user?->full_name ?? 'Account' }}</p>
                            <p class="truncate text-xs text-muted-foreground">{{ $user?->email }}</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2 rounded px-3 py-2 text-sm text-foreground transition hover:bg-accent">
                                <x-icon.log-out class="size-4" />
                                Log out
                            </button>
                        </form>
                    </div>
                </div>
                @endguest
            </header>

            {{-- Flash messages --}}
            @if(session('status') || session('success') || session('error'))
                <div class="px-4 pt-4 sm:px-6">
                    @if(session('success'))
                        <x-alert variant="success">{{ session('success') }}</x-alert>
                    @endif
                    @if(session('status'))
                        <x-alert variant="info">{{ session('status') }}</x-alert>
                    @endif
                    @if(session('error'))
                        <x-alert variant="error">{{ session('error') }}</x-alert>
                    @endif
                </div>
            @endif

            <main class="flex-1 px-4 py-6 sm:px-6">
                {{ $slot }}
            </main>
        </div>

        {{-- Toasts: any component can show one with $this->dispatch('toast', message: '…'). --}}
        <div
            x-data="{ toasts: [] }"
            x-on:toast.window="
                const id = Date.now() + Math.random();
                toasts.push({ id, message: $event.detail.message });
                setTimeout(() => toasts = toasts.filter(t => t.id !== id), 3500);
            "
            class="pointer-events-none fixed bottom-4 right-4 z-50 flex w-full max-w-xs flex-col gap-2"
        >
            <template x-for="toast in toasts" :key="toast.id">
                <div
                    x-transition
                    class="pointer-events-auto flex items-center gap-2 rounded-md border border-border bg-popover px-4 py-3 text-sm text-popover-foreground shadow-lg"
                >
                    <x-icon.circle-check class="size-4 shrink-0 text-primary" />
                    <span x-text="toast.message"></span>
                </div>
            </template>
        </div>
    </div>
</body>
</html>
