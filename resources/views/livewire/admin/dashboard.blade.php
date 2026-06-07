<div class="space-y-6">
    <div>
        <h2 class="font-serif text-2xl font-semibold">Welcome{{ $admin?->name ? ', '.\Illuminate\Support\Str::before($admin->name, ' ') : '' }}</h2>
        <p class="mt-1 text-sm text-muted-foreground">Platform overview.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Users" :value="number_format($userCount)" icon="users" />
        <x-stat label="Suppliers" :value="number_format($supplierCount)" icon="building-2" />
        <x-stat label="Wines" :value="number_format($productCount)" icon="wine" />
        <x-stat label="Orders" :value="number_format($orderCount)" icon="clipboard-list" />
    </div>

    <x-card title="Management">
        <a href="{{ route('admin.users') }}" class="flex items-center gap-3 rounded-md p-2 transition hover:bg-accent">
            <span class="flex size-9 items-center justify-center rounded-md bg-primary/10 text-primary"><x-icon.users class="size-5" /></span>
            <div>
                <p class="text-sm font-medium text-foreground">Users</p>
                <p class="text-sm text-muted-foreground">View accounts, change plans, remove users.</p>
            </div>
            <x-icon.chevron-right class="ml-auto size-4 text-muted-foreground" />
        </a>
    </x-card>
</div>
