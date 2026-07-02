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
        'warning' => ['border' => 'border-amber-400/60', 'value' => 'text-amber-600 dark:text-amber-400'],
        'danger' => ['border' => 'border-destructive/60', 'value' => 'text-destructive'],
    ];
    $on = $active && isset($tones[$tone]) ? $tones[$tone] : $tones['default'];
@endphp

{{-- Divided-row stat (a left rule, no card box) — the same treatment as the
     dashboard's headline figures, so every surface counts things the same way. --}}
<div {{ $attributes->merge(['class' => 'border-l-2 py-1 pl-4 '.$on['border']]) }}>
    <p class="font-mono text-2xl font-medium tabular-nums {{ $on['value'] }}">{{ $value }}</p>
    <p class="mt-1 text-xs uppercase tracking-wider text-muted-foreground">{{ $label }}</p>

    @isset($footer)
        <div class="mt-1 text-xs text-muted-foreground">{{ $footer }}</div>
    @endisset
</div>
