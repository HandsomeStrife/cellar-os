{{-- A wine attribute the SUPPLIER's list did not provide, gap-filled from a
     named class of source. The supplier's own data always takes precedence —
     this only ever renders where their list left a gap.
       source="vendor" — the shared wine-facts store (another supplier's list;
                         the source supplier is deliberately never named)
       source="lwin"   — the Liv-ex LWIN reference database --}}
@props(['source' => 'vendor'])
@php($label = $source === 'lwin' ? 'From the Liv-ex LWIN wine database - not provided by this supplier' : "Populated from another vendor's information - not provided by this supplier")
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 border-b border-dotted border-primary/50 cursor-help']) }}
    title="{{ $label }}">
    {{ $slot }}
    @if($source === 'lwin')
        <x-icon.book-open class="size-3 text-primary/70" aria-hidden="true" />
    @else
        <x-icon.sparkles class="size-3 text-primary/70" aria-hidden="true" />
    @endif
    <span class="sr-only">({{ $label }})</span>
</span>
