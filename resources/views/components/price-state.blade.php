@props([
    'state',
    'note' => null,
])

{{-- Shown in place of a figure when the supplier withheld the price. The
     supplier's own wording, where we have it, is the useful part. --}}
<span
    {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded border border-border bg-secondary/60 px-1.5 py-0.5 font-mono text-[0.7rem] font-medium uppercase tracking-wide text-muted-foreground']) }}
    title="{{ $note ?: $state->getDescription() }}"
>
    {{ $state->getLabel() }}
</span>
