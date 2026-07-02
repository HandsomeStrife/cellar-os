@props([
    'title' => 'Upgrade required',
    'message' => null,
    'plan' => null,
    'compact' => false,
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed border-border bg-card/50 text-center '.($compact ? 'p-6' : 'p-12')]) }}>
    <x-icon.lock class="size-7 text-muted-foreground/45" />
    <div>
        <p class="font-serif text-lg font-semibold">{{ $title }}</p>
        @if($message)
            <p class="mx-auto mt-1 max-w-md text-sm text-muted-foreground">{{ $message }}</p>
        @endif
        @if($plan)
            <p class="mt-1 text-sm text-muted-foreground">Available on the <span class="font-medium text-foreground">{{ $plan }}</span> plan and above.</p>
        @endif
    </div>
    @if(\Illuminate\Support\Facades\Route::has('pricing'))
        <x-button :href="route('pricing')" size="sm" wire:navigate>View plans</x-button>
    @endif
    {{ $slot }}
</div>
