@props([
    'placeholder' => 'Search…',
])

<div {{ $attributes->only('class')->class(['relative w-full']) }}>
    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-muted-foreground">
        <x-icon.search class="size-4" />
    </span>
    <input
        type="search"
        placeholder="{{ $placeholder }}"
        {{ $attributes->except('class')->merge([
            'class' => 'block w-full rounded-md border border-input bg-card py-2 pl-9 pr-3 text-sm text-foreground shadow-sm transition placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/40',
        ]) }}
    />
</div>
