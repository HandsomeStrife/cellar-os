@props([
    'model',
    'title' => null,
    'maxWidth' => 'lg',
])

@php
    $widths = [
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
    ];
    $width = $widths[$maxWidth] ?? $widths['lg'];
@endphp

<div
    x-data="{ open: @entangle($model) }"
    x-show="open"
    x-cloak
    x-on:keydown.escape.window="open = false"
    class="fixed inset-0 z-50 overflow-y-auto"
    role="dialog"
    aria-modal="true"
>
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-black/50" x-on:click="open = false"></div>

    <div class="flex min-h-full items-start justify-center p-4 sm:p-6">
        <div
            x-show="open"
            x-transition
            class="relative z-10 mt-10 w-full {{ $width }} rounded-lg border border-border bg-card text-card-foreground shadow-xl"
        >
            @if($title)
                <div class="flex items-center justify-between border-b border-border px-5 py-4">
                    <h2 class="font-serif text-lg font-semibold">{{ $title }}</h2>
                    <button type="button" x-on:click="open = false" class="text-muted-foreground transition hover:text-foreground">
                        <x-icon.x class="size-5" />
                    </button>
                </div>
            @endif

            <div class="px-5 py-4">
                {{ $slot }}
            </div>

            @isset($footer)
                <div class="flex items-center justify-end gap-2 border-t border-border px-5 py-4">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
