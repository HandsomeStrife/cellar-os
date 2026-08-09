@props([
    'icon' => null,
    // 'default' | 'danger'
    'variant' => 'default',
    'href' => null,
    // POST to this URL instead (for actions that are routes, not Livewire calls
    // — impersonation, logout). Renders a CSRF-protected form.
    'post' => null,
])

@php
    $classes = collect([
        'flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm transition focus-visible:outline-none',
        'disabled:pointer-events-none disabled:opacity-50',
        $variant === 'danger'
            ? 'text-destructive hover:bg-destructive/10 focus-visible:bg-destructive/10'
            : 'text-foreground hover:bg-accent focus-visible:bg-accent',
    ])->implode(' ');
@endphp

@if($post)
    <form method="POST" action="{{ $post }}">
        @csrf
        <button type="submit" role="menuitem" {{ $attributes->merge(['class' => $classes]) }}>
            @if($icon)<x-dynamic-component :component="'icon.'.$icon" class="size-4 shrink-0 opacity-70" />@endif
            <span>{{ $slot }}</span>
        </button>
    </form>
@elseif($href)
    <a href="{{ $href }}" role="menuitem" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<x-dynamic-component :component="'icon.'.$icon" class="size-4 shrink-0 opacity-70" />@endif
        <span>{{ $slot }}</span>
    </a>
@else
    <button type="button" role="menuitem" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<x-dynamic-component :component="'icon.'.$icon" class="size-4 shrink-0 opacity-70" />@endif
        <span>{{ $slot }}</span>
    </button>
@endif
