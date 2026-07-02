<div class="space-y-8">
    <x-page-header title="Back office" subtitle="Platform state and the queues that need an admin." />

    {{-- Platform figures --}}
    <div class="grid gap-x-8 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Users" :value="number_format($userCount)" />
        <x-stat label="Suppliers" :value="number_format($supplierCount)" />
        <x-stat label="Wines" :value="number_format($productCount)" />
        <x-stat label="Orders" :value="number_format($orderCount)" />
    </div>

    {{-- Work queues: each row is a real, current queue with a direct route in. --}}
    <section>
        <h3 class="font-serif text-lg font-semibold">Needs an admin</h3>
        <ul class="mt-3 divide-y divide-border border-y border-border">
            <li>
                <a href="{{ route('admin.enquiries') }}" class="flex items-center justify-between gap-4 py-3 transition hover:bg-accent/40">
                    <div>
                        <p class="text-sm font-medium text-foreground">New enquiries</p>
                        <p class="text-sm text-muted-foreground">Contact-form messages nobody has read yet.</p>
                    </div>
                    <span class="flex shrink-0 items-center gap-2">
                        <span @class([
                            'font-mono text-xl tabular-nums',
                            'font-semibold text-primary' => $newEnquiries > 0,
                            'text-muted-foreground' => $newEnquiries === 0,
                        ])>{{ number_format($newEnquiries) }}</span>
                        <x-icon.chevron-right class="size-4 text-muted-foreground" />
                    </span>
                </a>
            </li>
            <li>
                <a href="{{ route('admin.suppliers') }}" class="flex items-center justify-between gap-4 py-3 transition hover:bg-accent/40">
                    <div>
                        <p class="text-sm font-medium text-foreground">Documents awaiting analysis</p>
                        <p class="text-sm text-muted-foreground">Uploaded price lists that haven't been parsed.</p>
                    </div>
                    <span class="flex shrink-0 items-center gap-2">
                        <span @class([
                            'font-mono text-xl tabular-nums',
                            'font-semibold text-primary' => $awaitingAnalysis > 0,
                            'text-muted-foreground' => $awaitingAnalysis === 0,
                        ])>{{ number_format($awaitingAnalysis) }}</span>
                        <x-icon.chevron-right class="size-4 text-muted-foreground" />
                    </span>
                </a>
            </li>
            <li>
                <a href="{{ route('admin.costs') }}" class="flex items-center justify-between gap-4 py-3 transition hover:bg-accent/40">
                    <div>
                        <p class="text-sm font-medium text-foreground">AI spend, last 7 days</p>
                        <p class="text-sm text-muted-foreground">{{ number_format($aiWeek['calls']) }} billable {{ \Illuminate\Support\Str::plural('call', $aiWeek['calls']) }}. The full ledger is in AI costs.</p>
                    </div>
                    <span class="flex shrink-0 items-center gap-2">
                        <span class="font-mono text-xl tabular-nums text-muted-foreground">${{ number_format($aiWeek['cost_usd'], 2) }}</span>
                        <x-icon.chevron-right class="size-4 text-muted-foreground" />
                    </span>
                </a>
            </li>
        </ul>
    </section>
</div>
