@props([
    'label' => null,
])

@php
    $name = $attributes->get('name');
    $id = $attributes->get('id', $name);
@endphp

<div class="space-y-1.5">
    <label for="{{ $id }}" class="flex items-center gap-2 text-sm text-foreground select-none">
        <input type="checkbox" {{ $attributes->merge(['id' => $id])->class([
            'size-4 rounded border-input bg-card text-primary shadow-sm transition focus:ring-2 focus:ring-ring/40 focus:ring-offset-0',
        ]) }} />
        @if($label)
            <span>{{ $label }}</span>
        @endif
        {{ $slot }}
    </label>

    @if($name)
        <x-input.error :messages="$errors->get($name)" />
    @endif
</div>
