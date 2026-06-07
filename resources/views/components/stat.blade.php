@props([
    'label',
    'value',
    'icon' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-border bg-card p-5 shadow-sm']) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-sm font-medium text-muted-foreground">{{ $label }}</p>
            <p class="mt-2 truncate font-serif text-3xl font-semibold text-foreground">{{ $value }}</p>
        </div>
        @if($icon)
            <span class="flex size-10 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                <x-dynamic-component :component="'icon.'.$icon" class="size-5" />
            </span>
        @endif
    </div>

    @isset($footer)
        <div class="mt-3 text-xs text-muted-foreground">{{ $footer }}</div>
    @endisset
</div>
