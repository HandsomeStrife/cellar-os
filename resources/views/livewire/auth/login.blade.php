<div>
    <x-card>
        <x-slot:header>
            <h1 class="font-serif text-2xl font-semibold leading-tight">Welcome back</h1>
            <p class="mt-1 text-sm text-muted-foreground">Sign in to your CellarOS account.</p>
        </x-slot:header>

        <form wire:submit="login" class="space-y-5">
            <x-input.email label="Email" name="email" wire:model="email" required autofocus />

            <x-input.password label="Password" name="password" wire:model="password" autocomplete="current-password" required />

            <div class="flex items-center justify-between">
                <x-input.checkbox label="Remember me" name="remember" wire:model="remember" />

                @if(Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-sm text-primary hover:underline" wire:navigate>Forgot password?</a>
                @endif
            </div>

            <x-button type="submit" class="w-full" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="login">Log in</span>
                <span wire:loading wire:target="login">Signing in…</span>
            </x-button>
        </form>
    </x-card>

    <p class="mt-4 text-center text-sm text-muted-foreground">
        Don't have an account?
        <a href="{{ route('register') }}" class="font-medium text-primary hover:underline" wire:navigate>Create one</a>
    </p>
</div>
