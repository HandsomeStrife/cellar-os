<div>
    <x-card>
        <x-slot:header>
            <h1 class="font-serif text-2xl font-semibold leading-tight">Forgot your password?</h1>
            <p class="mt-1 text-sm text-muted-foreground">We'll email you a secure link to choose a new one.</p>
        </x-slot:header>

        @if(session('status'))
            <div class="mb-5">
                <x-alert variant="success">{{ session('status') }}</x-alert>
            </div>
        @endif

        <form wire:submit="sendResetLink" class="space-y-5">
            <x-input.email label="Email" name="email" wire:model="email" required autofocus />

            <x-button type="submit" class="w-full" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="sendResetLink">Email reset link</span>
                <span wire:loading wire:target="sendResetLink">Sending…</span>
            </x-button>
        </form>
    </x-card>

    <p class="mt-4 text-center text-sm text-muted-foreground">
        <a href="{{ route('login') }}" class="font-medium text-primary hover:underline" wire:navigate>Back to sign in</a>
    </p>
</div>
