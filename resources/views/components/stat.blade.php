@props([
    'label',
    'value',
    'icon' => null,
    'tone' => 'default', // default | warning | danger
    'active' => false,    // tone styling only applies when there's something to flag
])

@php
    $tones = [
        'default' => ['border' => 'border-border', 'value' => 'text-foreground', 'badge' => 'bg-primary/10 text-primary'],
        'warning' => ['border' => 'border-amber-400/50', 'value' => 'text-amber-600 dark:text-amber-400', 'badge' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400'],
        'danger' => ['border' => 'border-destructive/50', 'value' => 'text-destructive', 'badge' => 'bg-destructive/10 text-destructive'],
    ];
    $on = $active && isset($tones[$tone]) ? $tones[$tone] : $tones['default'];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-lg border bg-card p-5 shadow-sm '.$on['border']]) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-sm font-medium text-muted-foreground">{{ $label }}</p>
            <p class="mt-2 truncate font-serif text-3xl font-semibold {{ $on['value'] }}">{{ $value }}</p>
        </div>
        @if($icon)
            <span class="flex size-10 shrink-0 items-center justify-center rounded-md {{ $on['badge'] }}">
                <x-dynamic-component :component="'icon.'.$icon" class="size-5" />
            </span>
        @endif
    </div>

    @isset($footer)
        <div class="mt-3 text-xs text-muted-foreground">{{ $footer }}</div>
    @endisset
</div>
