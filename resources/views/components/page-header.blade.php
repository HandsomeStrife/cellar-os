@props([
    'eyebrow' => null,
    'title',
    'subtitle' => null,
])

{{-- Page masthead: a mono eyebrow + serif title, matching the dashboard.
     Pass an `actions` slot for the page's primary controls. --}}
<div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-3 border-b border-border pb-5">
    <div class="min-w-0">
        @if($eyebrow)
            <p class="font-mono text-xs uppercase tracking-[0.2em] text-muted-foreground">{{ $eyebrow }}</p>
        @endif
        <h2 class="mt-1.5 font-serif text-2xl font-semibold tracking-tight">{{ $title }}</h2>
        @if($subtitle)
            <p class="mt-1 text-sm text-muted-foreground">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
