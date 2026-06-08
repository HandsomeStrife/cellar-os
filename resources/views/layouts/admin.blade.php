@props(['title' => null])

@php
    $nav = [
        ['label' => 'Dashboard', 'icon' => 'layout-dashboard', 'route' => 'admin.dashboard'],
        ['label' => 'Users', 'icon' => 'users', 'route' => 'admin.users'],
    ];
    $admin = auth('admin')->user();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · CellarOS Admin' : 'CellarOS Admin' }}</title>
    <link rel="icon" type="image/svg+xml" href="/cellar-os-logo.svg">
    <script>
        (function () {
            const apply = () => {
                const dark = localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
            };
            apply();
            document.addEventListener('livewire:navigated', apply);
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-background text-foreground antialiased">
    <div x-data="{ sidebarOpen: false }" class="flex min-h-screen">
        <div x-show="sidebarOpen" x-cloak x-transition.opacity x-on:click="sidebarOpen = false" class="fixed inset-0 z-30 bg-black/50 lg:hidden"></div>

        <aside x-bind:class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'" class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-sidebar text-sidebar-foreground transition-transform lg:static">
            <div class="flex h-16 items-center gap-2.5 border-b border-sidebar-border px-5">
                <span class="flex size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                    <x-icon.shield class="size-5" />
                </span>
                <span class="font-serif text-lg font-semibold tracking-tight">Admin</span>
            </div>
            <nav class="flex-1 space-y-1 px-3 py-4">
                @foreach($nav as $item)
                    @php($active = request()->routeIs($item['route'].'*'))
                    <a href="{{ route($item['route']) }}" @class([
                        'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                        'bg-sidebar-primary text-sidebar-primary-foreground' => $active,
                        'text-sidebar-foreground/80 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground' => ! $active,
                    ])>
                        <x-dynamic-component :component="'icon.'.$item['icon']" class="size-5 shrink-0" />
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-20 flex h-16 items-center gap-4 border-b border-border bg-background/95 px-4 backdrop-blur sm:px-6">
                <button x-on:click="sidebarOpen = true" class="text-muted-foreground hover:text-foreground lg:hidden"><x-icon.menu class="size-6" /></button>
                <div class="min-w-0 flex-1">
                    @if($title)<h1 class="truncate font-serif text-lg font-semibold">{{ $title }}</h1>@endif
                </div>
                <span class="hidden text-sm text-muted-foreground sm:block">{{ $admin?->name }}</span>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <x-button type="submit" variant="outline" size="sm">
                        <x-icon.log-out class="size-4" /> Log out
                    </x-button>
                </form>
            </header>

            @if(session('success') || session('error'))
                <div class="px-4 pt-4 sm:px-6">
                    @if(session('success'))<x-alert variant="success">{{ session('success') }}</x-alert>@endif
                    @if(session('error'))<x-alert variant="error">{{ session('error') }}</x-alert>@endif
                </div>
            @endif

            <main class="flex-1 px-4 py-6 sm:px-6">{{ $slot }}</main>
        </div>

        <div x-data="{ toasts: [] }" x-on:toast.window="const id=Date.now()+Math.random(); toasts.push({id,message:$event.detail.message}); setTimeout(()=>toasts=toasts.filter(t=>t.id!==id),3500)" class="pointer-events-none fixed bottom-4 right-4 z-50 flex w-full max-w-xs flex-col gap-2">
            <template x-for="toast in toasts" :key="toast.id">
                <div x-transition class="pointer-events-auto flex items-center gap-2 rounded-md border border-border bg-popover px-4 py-3 text-sm text-popover-foreground shadow-lg">
                    <x-icon.circle-check class="size-4 shrink-0 text-primary" /><span x-text="toast.message"></span>
                </div>
            </template>
        </div>
    </div>
</body>
</html>
