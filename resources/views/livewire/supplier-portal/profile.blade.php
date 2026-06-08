<div class="space-y-6">
    <div>
        <h2 class="font-serif text-2xl font-semibold">Company profile</h2>
        <p class="mt-1 text-sm text-muted-foreground">Your company details as held by CellarOS. Contact us to update them.</p>
    </div>

    <x-card :title="$supplier?->name">
        <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Contact</dt>
                <dd class="mt-1 text-sm">{{ $supplier?->contact ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Email</dt>
                <dd class="mt-1 text-sm">{{ $supplier?->email ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Phone</dt>
                <dd class="mt-1 text-sm">{{ $supplier?->phone ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Website</dt>
                <dd class="mt-1 text-sm">{{ $supplier?->website ?: '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Address</dt>
                <dd class="mt-1 text-sm">
                    {{ collect([$supplier?->address, $supplier?->city, $supplier?->postcode, $supplier?->country])->filter()->implode(', ') ?: '—' }}
                </dd>
            </div>
        </dl>
    </x-card>

    <x-card title="Team">
        <ul class="divide-y divide-border">
            @foreach($colleagues as $colleague)
                <li class="flex items-center justify-between gap-3 py-3">
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ $colleague->name }}</p>
                        <p class="truncate text-xs text-muted-foreground">{{ $colleague->email }}</p>
                    </div>
                    @if($colleague->id === $supplierUser?->id)
                        <x-badge color="wine">You</x-badge>
                    @endif
                </li>
            @endforeach
        </ul>
    </x-card>
</div>
