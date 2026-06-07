@props([
    'variant' => 'info',
])

@php
    $variants = [
        'success' => ['wrap' => 'border-green-500/30 bg-green-50 text-green-800 dark:bg-green-500/10 dark:text-green-300', 'icon' => 'circle-check'],
        'error' => ['wrap' => 'border-destructive/30 bg-destructive/10 text-destructive', 'icon' => 'circle-alert'],
        'warning' => ['wrap' => 'border-amber-500/30 bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300', 'icon' => 'circle-alert'],
        'info' => ['wrap' => 'border-border bg-secondary text-secondary-foreground', 'icon' => 'circle-alert'],
    ];
    $config = $variants[$variant] ?? $variants['info'];
@endphp

<div {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-md border px-4 py-3 text-sm '.$config['wrap']]) }}>
    <x-dynamic-component :component="'icon.'.$config['icon']" class="mt-0.5 size-4 shrink-0" />
    <div class="space-y-0.5">
        {{ $slot }}
    </div>
</div>
