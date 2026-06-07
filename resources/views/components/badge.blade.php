@props([
    'color' => 'gray',
])

@php
    $colors = [
        'gray' => 'bg-secondary text-secondary-foreground',
        'amber' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
        'blue' => 'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-300',
        'green' => 'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-300',
        'emerald' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
        'red' => 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300',
        'wine' => 'bg-primary/10 text-primary dark:bg-primary/20',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium '.($colors[$color] ?? $colors['gray'])]) }}>
    {{ $slot }}
</span>
