@props([
    'href' => null,
    'showText' => true,
])

@php($tag = $href ? 'a' : 'div')

<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <span class="flex size-9 items-center justify-center rounded-md bg-primary text-primary-foreground shadow-sm">
        <x-icon.wine class="size-5" />
    </span>
    @if($showText)
        <span class="font-serif text-xl font-semibold tracking-tight text-foreground">
            Cellar<span class="text-primary">OS</span>
        </span>
    @endif
</{{ $tag }}>
