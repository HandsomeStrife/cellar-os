@props([
    'label' => null,
    'hint' => null,
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

    <textarea {{ $attributes->merge(['id' => $id, 'rows' => 4])->class([
        'block w-full rounded-md border border-input bg-card px-3 py-2 text-sm text-foreground shadow-sm transition placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring/40 disabled:cursor-not-allowed disabled:opacity-50',
        'border-destructive focus:border-destructive focus:ring-destructive/30' => $hasError,
    ]) }}>{{ $slot }}</textarea>

    @if($hint)
        <p class="text-xs text-muted-foreground">{{ $hint }}</p>
    @endif

    @if($name)
        <x-input.error :messages="$errors->get($name)" />
    @endif
</div>
