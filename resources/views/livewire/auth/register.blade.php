<div>
    <x-card>
        <x-slot:header>
            <h1 class="font-serif text-2xl font-semibold leading-tight">Create your account</h1>
            <p class="mt-1 text-sm text-muted-foreground">Start managing your wine trade with CellarOS.</p>
        </x-slot:header>

        <form wire:submit="register" class="space-y-5">
            <x-input.text label="Full name" name="full_name" wire:model="full_name" autocomplete="name" required autofocus />

            <x-input.email label="Email" name="email" wire:model="email" required />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-input.text label="Company / venue" name="company_name" wire:model="company_name" hint="Becomes your first venue" />
                <x-input.select
                    label="Profession"
                    name="profession"
                    :options="collect($professions)->mapWithKeys(fn ($p) => [$p => $p])->all()"
                    placeholder="Select…"
                    wire:model="profession"
                />
            </div>

            <x-input.select
                label="Base currency"
                name="base_currency"
                :options="collect($currencies)->mapWithKeys(fn ($c) => [$c => $c])->all()"
                wire:model="base_currency"
            />

            <x-input.password label="Password" name="password" wire:model="password" autocomplete="new-password" required />

            <x-input.password label="Confirm password" name="password_confirmation" wire:model="password_confirmation" autocomplete="new-password" required />

            <x-button type="submit" class="w-full" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="register">Create account</span>
                <span wire:loading wire:target="register">Creating…</span>
            </x-button>
        </form>
    </x-card>

    <p class="mt-4 text-center text-sm text-muted-foreground">
        Already have an account?
        <a href="{{ route('login') }}" class="font-medium text-primary hover:underline" wire:navigate>Sign in</a>
    </p>
</div>
