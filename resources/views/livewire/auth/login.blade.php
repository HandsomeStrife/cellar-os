<div>
    <x-card class="shadow-md">
        <x-slot:header>
            <p class="font-mono text-xs uppercase tracking-[0.22em] text-primary">Welcome back</p>
            <h1 class="mt-2 font-display text-2xl font-semibold tracking-tight">Sign in to CellarOS</h1>
            <p class="mt-1 text-sm text-muted-foreground">Enter your details to reach your workspace.</p>
        </x-slot:header>

        <form wire:submit="login" class="space-y-5">
            <x-input.email label="Email" name="email" wire:model="email" required autofocus />

            <x-input.password label="Password" name="password" wire:model="password" autocomplete="current-password" required />

            <div class="flex items-center justify-between">
                <x-input.checkbox label="Remember me" name="remember" wire:model="remember" />

                @if(Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-sm font-medium text-primary hover:underline" wire:navigate>Forgot password?</a>
                @endif
            </div>

            <x-button type="submit" size="lg" class="w-full" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="login">Log in</span>
                <span wire:loading wire:target="login">Signing in…</span>
            </x-button>
        </form>
    </x-card>

    <p class="mt-6 text-center text-sm text-muted-foreground">
        Don't have an account yet?
        <a href="{{ route('home').'#contact' }}" class="font-medium text-primary hover:underline">Get in touch</a>
        and we'll set you up.
    </p>
</div>
