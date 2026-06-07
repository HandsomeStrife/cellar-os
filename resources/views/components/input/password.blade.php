@props([
    'label' => null,
    'hint' => null,
])

@php
    $name = $attributes->get('name', 'password');
    $id = $attributes->get('id', $name);
    $hasError = $errors->has($name);
@endphp

<div class="space-y-1.5" x-data="{ show: false }">
    @if($label)
        <x-input.label :for="$id">{{ $label }}</x-input.label>
    @endif

    <div class="relative">
        <input
            x-bind:type="show ? 'text' : 'password'"
            type="password"
            {{ $attributes->merge(['name' => $name, 'id' => $id, 'autocomplete' => 'current-password'])->class([
                'block w-full rounded-md border border-input bg-card px-3 py-2 pr-10 text-sm text-foreground shadow-sm transition placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/40 disabled:cursor-not-allowed disabled:opacity-50',
                'border-destructive focus:border-destructive focus:ring-destructive/30' => $hasError,
            ]) }}
        />
        <button
            type="button"
            x-on:click="show = !show"
            x-bind:aria-label="show ? 'Hide password' : 'Show password'"
            class="absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground transition hover:text-foreground"
        >
            <x-icon.eye x-show="!show" class="size-4" />
            <x-icon.eye-off x-show="show" x-cloak class="size-4" />
        </button>
    </div>

    @if($hint)
        <p class="text-xs text-muted-foreground">{{ $hint }}</p>
    @endif

    <x-input.error :messages="$errors->get($name)" />
</div>
