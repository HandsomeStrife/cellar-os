<div>
    <x-card>
        <x-slot:header>
            <h1 class="font-serif text-2xl font-semibold leading-tight">Supplier sign in</h1>
            <p class="mt-1 text-sm text-muted-foreground">Upload and manage your portfolios with CellarOS.</p>
        </x-slot:header>

        @if(session('status'))
            <div class="mb-5">
                <x-alert variant="success">{{ session('status') }}</x-alert>
            </div>
        @endif

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

    <p class="mt-4 text-center text-sm text-muted-foreground">
        <a href="{{ route('supplier.password.request') }}" class="font-medium text-primary hover:underline" wire:navigate>Forgot your password?</a>
    </p>
</div>
