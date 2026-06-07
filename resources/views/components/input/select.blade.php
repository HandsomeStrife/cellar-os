@props([
    'label' => null,
    'hint' => null,
    'options' => [],
    'placeholder' => null,
    'selected' => null,
])

@php
    $name = $attributes->get('name');
    $id = $attributes->get('id', $name);
    $hasError = $name && $errors->has($name);
@endphp

<div class="space-y-1.5">
    @if($label)
        <x-input.label :for="$id">{{ $label }}</x-input.label>
    @endif

    <select {{ $attributes->merge(['id' => $id])->class([
        'block w-full rounded-md border border-input bg-card px-3 py-2 text-sm text-foreground shadow-sm transition focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/40 disabled:cursor-not-allowed disabled:opacity-50',
        'border-destructive focus:border-destructive focus:ring-destructive/30' => $hasError,
    ]) }}>
        @if($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif

        @forelse($options as $value => $optionLabel)
            <option value="{{ $value }}" @selected((string) $value === (string) $selected)>{{ $optionLabel }}</option>
        @empty
            {{ $slot }}
        @endforelse
    </select>

    @if($hint)
        <p class="text-xs text-muted-foreground">{{ $hint }}</p>
    @endif

    @if($name)
        <x-input.error :messages="$errors->get($name)" />
    @endif
</div>
