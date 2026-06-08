@props([
    'icon' => 'wine',
    'title' => 'Nothing here yet',
    'message' => null,
])

<div class="flex flex-col items-center justify-center gap-3 py-12 text-center">
    <span class="flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary">
        <x-dynamic-component :component="'icon.'.$icon" class="size-6" />
    </span>
    <div>
        <p class="font-medium text-foreground">{{ $title }}</p>
        @if($message)
            <p class="mx-auto mt-1 max-w-md text-sm text-muted-foreground">{{ $message }}</p>
        @endif
    </div>
    {{ $slot }}
</div>
