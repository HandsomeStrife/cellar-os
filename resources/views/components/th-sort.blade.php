@props([
    'column',
    'sort',
    'direction' => 'asc',
    'align' => 'left',
])

<th {{ $attributes->merge(['class' => 'px-3 py-2 text-'.$align.' text-xs font-medium uppercase tracking-wide text-muted-foreground']) }}>
    <button type="button" wire:click="sortBy('{{ $column }}')" class="inline-flex items-center gap-1 transition hover:text-foreground {{ $sort === $column ? 'text-foreground' : '' }}">
        {{ $slot }}
        @if($sort === $column)
            <x-icon.chevron-down class="size-3.5 transition {{ $direction === 'asc' ? 'rotate-180' : '' }}" />
        @endif
    </button>
</th>
