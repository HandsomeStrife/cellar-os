@props([
    'href' => null,
    'showText' => true,
])

@php($tag = $href ? 'a' : 'div')

<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <span class="relative flex size-9 items-center justify-center rounded-full bg-gradient-to-br from-primary to-[#5b1226] text-primary-foreground shadow-sm ring-1 ring-inset ring-white/15">
        <x-icon.wine class="size-5" />
    </span>
    @if($showText)
        <span class="font-display text-xl font-semibold tracking-tight text-foreground">Cellar<span class="text-primary">OS</span></span>
    @endif
</{{ $tag }}>
