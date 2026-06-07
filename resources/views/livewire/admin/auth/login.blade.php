<div>
    <x-card>
        <x-slot:header>
            <h1 class="font-serif text-2xl font-semibold leading-tight">Administrator sign in</h1>
            <p class="mt-1 text-sm text-muted-foreground">Back-office access for CellarOS staff.</p>
        </x-slot:header>

        <form wire:submit="login" class="space-y-5">
            <x-input.email label="Email" name="email" wire:model="email" required autofocus />
            <x-input.password label="Password" name="password" wire:model="password" required />
            <x-input.checkbox label="Remember me" name="remember" wire:model="remember" />
            <x-button type="submit" class="w-full" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="login">Sign in</span>
                <span wire:loading wire:target="login">Signing in…</span>
            </x-button>
        </form>
    </x-card>
</div>
