@props([
    'title' => null,
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-border bg-card text-card-foreground shadow-sm']) }}>
    @if($title || $subtitle || isset($header))
        <div class="border-b border-border px-5 py-4">
            @isset($header)
                {{ $header }}
            @else
                @if($title)
                    <h3 class="font-serif text-lg font-semibold leading-tight">{{ $title }}</h3>
                @endif
                @if($subtitle)
                    <p class="mt-0.5 text-sm text-muted-foreground">{{ $subtitle }}</p>
                @endif
            @endisset
        </div>
    @endif

    <div class="px-5 py-4">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="border-t border-border px-5 py-4">
            {{ $footer }}
        </div>
    @endisset
</div>
