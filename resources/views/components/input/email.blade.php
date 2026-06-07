@props([
    'label' => null,
    'hint' => null,
])

{{-- Convenience wrapper: defaults to type/name/autocomplete "email", all overridable. --}}
<x-input.text
    :label="$label"
    :hint="$hint"
    type="email"
    autocomplete="email"
    {{ $attributes->merge(['name' => 'email']) }}
/>
