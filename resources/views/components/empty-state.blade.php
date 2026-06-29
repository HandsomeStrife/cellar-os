@props([
    'icon' => 'wine',
    'title' => 'Nothing here yet',
    'message' => null,
])

<div class="flex flex-col items-center justify-center gap-3 py-14 text-center">
    <x-dynamic-component :component="'icon.'.$icon" class="size-7 text-muted-foreground/45" />
    <div>
        <p class="font-serif text-lg font-semibold text-foreground">{{ $title }}</p>
        @if($message)
            <p class="mx-auto mt-1 max-w-md text-sm text-muted-foreground">{{ $message }}</p>
        @endif
    </div>
    {{ $slot }}
</div>
