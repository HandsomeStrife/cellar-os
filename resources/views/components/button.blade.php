@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'href' => null,
])

@php
    $base = 'inline-flex items-center justify-center gap-2 font-medium rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-ring/50 focus:ring-offset-2 focus:ring-offset-background disabled:pointer-events-none disabled:opacity-50';

    $variants = [
        'primary' => 'bg-primary text-primary-foreground hover:bg-primary/90 shadow-sm',
        'secondary' => 'bg-secondary text-secondary-foreground hover:bg-secondary/80',
        'outline' => 'border border-input bg-transparent text-foreground hover:bg-accent hover:text-accent-foreground',
        'ghost' => 'bg-transparent text-foreground hover:bg-accent hover:text-accent-foreground',
        'danger' => 'bg-destructive text-destructive-foreground hover:bg-destructive/90 shadow-sm',
    ];

    $sizes = [
        'sm' => 'px-3 py-1.5 text-xs',
        'md' => 'px-4 py-2 text-sm',
        'lg' => 'px-6 py-2.5 text-base',
    ];

    $classes = $base.' '.($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']);
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
