@props([
    'label',
    'value',
    'icon' => null,      // accepted for back-compat; stats read typographically now (no icon badge)
    'tone' => 'default', // default | warning | danger
    'active' => false,   // tone styling only applies when there's something to flag
])

@php
    $tones = [
        'default' => ['border' => 'border-border', 'value' => 'text-foreground'],
        'warning' => ['border' => 'border-amber-400/50', 'value' => 'text-amber-600 dark:text-amber-400'],
        'danger' => ['border' => 'border-destructive/50', 'value' => 'text-destructive'],
    ];
    $on = $active && isset($tones[$tone]) ? $tones[$tone] : $tones['default'];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-lg border bg-card p-5 '.$on['border']]) }}>
    <p class="text-xs uppercase tracking-wider text-muted-foreground">{{ $label }}</p>
    <p class="mt-2 truncate font-mono text-3xl font-semibold tabular-nums {{ $on['value'] }}">{{ $value }}</p>

    @isset($footer)
        <div class="mt-2 text-xs text-muted-foreground">{{ $footer }}</div>
    @endisset
</div>
