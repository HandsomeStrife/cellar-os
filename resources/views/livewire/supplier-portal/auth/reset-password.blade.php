<div>
    <x-card>
        <x-slot:header>
            <h1 class="font-serif text-2xl font-semibold leading-tight">Set your password</h1>
            <p class="mt-1 text-sm text-muted-foreground">Choose a password to finish setting up your supplier account.</p>
        </x-slot:header>

        <form wire:submit="resetPassword" class="space-y-5">
            <x-input.email label="Email" name="email" wire:model="email" required />

            <x-input.password label="New password" name="password" wire:model="password" autocomplete="new-password" required />

            <x-input.password label="Confirm new password" name="password_confirmation" wire:model="password_confirmation" autocomplete="new-password" required />

            <x-button type="submit" class="w-full" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="resetPassword">Save password</span>
                <span wire:loading wire:target="resetPassword">Saving…</span>
            </x-button>
        </form>
    </x-card>

    <p class="mt-4 text-center text-sm text-muted-foreground">
        <a href="{{ route('supplier.login') }}" class="font-medium text-primary hover:underline" wire:navigate>Back to sign in</a>
    </p>
</div>
