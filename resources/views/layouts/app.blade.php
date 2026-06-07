@props(['title' => null])

@php
    // Sidebar IA. Items whose route doesn't exist yet render inert ("soon").
    $nav = [
        ['label' => 'Dashboard', 'icon' => 'layout-dashboard', 'route' => 'dashboard'],
        ['label' => 'Catalogue', 'icon' => 'wine', 'route' => 'catalogue'],
        ['label' => 'Suppliers', 'icon' => 'users', 'route' => 'suppliers'],
        ['label' => 'Inventory', 'icon' => 'package', 'route' => 'inventory'],
        ['label' => 'Orders', 'icon' => 'clipboard-list', 'route' => 'orders'],
        ['label' => 'Import', 'icon' => 'upload', 'route' => 'import'],
        ['label' => 'Map', 'icon' => 'map', 'route' => 'map'],
        ['label' => 'Pricing', 'icon' => 'credit-card', 'route' => 'pricing'],
    ];
    $user = auth()->user();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' — CellarOS' : 'CellarOS' }}</title>

    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-background text-foreground antialiased">
    <div x-data="{ sidebarOpen: false }" class="flex min-h-screen">
        {{-- Mobile backdrop --}}
        <div
            x-show="sidebarOpen"
            x-cloak
            x-transition.opacity
            x-on:click="sidebarOpen = false"
            class="fixed inset-0 z-30 bg-black/50 lg:hidden"
        ></div>

        {{-- Sidebar --}}
        <aside
            x-bind:class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
            class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-sidebar text-sidebar-foreground transition-transform lg:static lg:translate-x-0"
        >
            <div class="flex h-16 items-center justify-between border-b border-sidebar-border px-5">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2.5">
                    <span class="flex size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                        <x-icon.wine class="size-5" />
                    </span>
                    <span class="font-serif text-lg font-semibold tracking-tight">Cellar<span class="text-sidebar-primary">OS</span></span>
                </a>
                <button x-on:click="sidebarOpen = false" class="text-sidebar-foreground/70 hover:text-sidebar-foreground lg:hidden">
                    <x-icon.x class="size-5" />
                </button>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                @foreach($nav as $item)
                    @php($exists = \Illuminate\Support\Facades\Route::has($item['route']))
                    @php($active = $exists && request()->routeIs($item['route'].'*'))
                    @if($exists)
                        <a
                            href="{{ route($item['route']) }}"
                            @class([
                                'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                                'bg-sidebar-primary text-sidebar-primary-foreground' => $active,
                                'text-sidebar-foreground/80 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground' => ! $active,
                            ])
                        >
                            <x-dynamic-component :component="'icon.'.$item['icon']" class="size-5 shrink-0" />
                            {{ $item['label'] }}
                        </a>
                    @else
                        <span class="flex cursor-not-allowed items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-sidebar-foreground/35" title="Coming soon">
                            <x-dynamic-component :component="'icon.'.$item['icon']" class="size-5 shrink-0" />
                            {{ $item['label'] }}
                            <span class="ml-auto text-[10px] uppercase tracking-wide text-sidebar-foreground/30">soon</span>
                        </span>
                    @endif
                @endforeach
            </nav>
        </aside>

        {{-- Main column --}}
        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-20 flex h-16 items-center gap-4 border-b border-border bg-background/95 px-4 backdrop-blur sm:px-6">
                <button x-on:click="sidebarOpen = true" class="text-muted-foreground hover:text-foreground lg:hidden">
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

                {{-- User menu --}}
                <div x-data="{ open: false }" class="relative">
                    <button x-on:click="open = ! open" class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm transition hover:bg-accent">
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
    </div>
</body>
</html>
