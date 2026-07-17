@props([
    'model',
    'title' => null,
    'maxWidth' => 'md',
])

@php
    $widths = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
    ];
    $width = $widths[$maxWidth] ?? $widths['md'];
@endphp

<div
    x-data="{ open: @entangle($model) }"
    x-show="open"
    x-cloak
    x-on:keydown.escape.window="open = false"
    class="fixed inset-0 z-50"
    role="dialog"
    aria-modal="true"
>
    <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/50" x-on:click="open = false"></div>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="absolute inset-y-0 right-0 flex w-full {{ $width }} flex-col border-l border-border bg-card text-card-foreground shadow-xl"
    >
        <div class="flex items-start justify-between gap-4 border-b border-border px-5 py-4">
            @isset($header)
                <div class="min-w-0">{{ $header }}</div>
            @else
                <h2 class="font-serif text-lg font-semibold">{{ $title }}</h2>
            @endisset
            <button type="button" x-on:click="open = false" aria-label="Close panel" class="-m-1 flex size-8 shrink-0 items-center justify-center rounded-md text-muted-foreground transition hover:bg-accent hover:text-foreground">
                <x-icon.x class="size-5" />
            </button>
        </div>

        <div class="flex-1 overflow-y-auto px-5 py-4">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="flex items-center justify-end gap-2 border-t border-border px-5 py-4">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
