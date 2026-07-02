<div class="space-y-6">
    <div class="relative w-full max-w-xs">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-muted-foreground">
            <x-icon.search class="size-4" />
        </span>
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search companies…" class="block w-full rounded-md border border-input bg-card py-2 pl-9 pr-3 text-sm text-foreground shadow-sm transition placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring/40" />
    </div>

    @if($companies->total() === 0)
        <x-card><x-empty-state icon="building-2" title="No companies found" message="No accounts match your search." /></x-card>
    @else
        <div class="overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
            <table class="w-full text-sm">
                <thead class="border-b border-border bg-secondary/40">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Company</th>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Plan</th>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Users</th>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Venues</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($companies as $company)
                        <tr wire:key="company-{{ $company->id }}" class="hover:bg-accent/40">
                            <td class="px-3 py-2.5 font-medium">{{ $company->name }}</td>
                            <td class="px-3 py-2.5"><x-badge color="wine">{{ $company->plan->getLabel() }}</x-badge></td>
                            <td class="px-3 py-2.5 text-muted-foreground">{{ $counts[$company->id]['users'] ?? 0 }}</td>
                            <td class="px-3 py-2.5 text-muted-foreground">{{ $counts[$company->id]['venues'] ?? 0 }}</td>
                            <td class="px-3 py-2.5 text-right">
                                <x-button :href="route('admin.companies.show', $company->uuid)" wire:navigate variant="outline" size="sm">Manage</x-button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div>{{ $companies->links() }}</div>
    @endif
</div>
