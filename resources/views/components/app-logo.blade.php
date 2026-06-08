@props([
    'href' => null,
    'showText' => true,
    'markClass' => 'text-primary', // pass '' to let the mark inherit the current text colour
])

@php($tag = $href ? 'a' : 'div')

<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <x-icon.logo class="size-8 shrink-0 {{ $markClass }}" />
    @if($showText)
        <span class="font-display text-xl font-semibold tracking-tight">Cellar<span class="text-primary">OS</span></span>
    @endif
</{{ $tag }}>
