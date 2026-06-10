{{-- A wine attribute gap-filled from the shared wine-facts store (another
     vendor's list). The source vendor is deliberately never named. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 border-b border-dotted border-primary/50 cursor-help']) }}
    title="Populated from another vendor's information">
    {{ $slot }}
    <x-icon.sparkles class="size-3 text-primary/70" aria-hidden="true" />
    <span class="sr-only">(populated from another vendor's information)</span>
</span>
