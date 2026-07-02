<div class="space-y-6">
    <p class="text-sm text-muted-foreground">
        Every billable Claude API call made by the parsing pipeline (column mappings, document studies, extractions, LWIN matching), priced in USD at the model's published rates. Pattern-mode and tabular re-parses run deterministically and cost nothing, so they never appear here.
    </p>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat label="All-time spend" :value="'$'.number_format($allTime['cost_usd'], 2)" icon="circle-dollar-sign" />
        <x-stat label="Last 30 days" :value="'$'.number_format($last30['cost_usd'], 2)" icon="calendar" />
        <x-stat label="Last 7 days" :value="'$'.number_format($last7['cost_usd'], 2)" icon="calendar-clock" />
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat label="API calls" :value="number_format($allTime['calls'])" icon="zap" />
        <x-stat label="Input tokens" :value="number_format($allTime['input_tokens'])" icon="arrow-down-to-line" />
        <x-stat label="Output tokens" :value="number_format($allTime['output_tokens'])" icon="arrow-up-from-line" />
    </div>

    @if($byModel->isNotEmpty())
        <x-card title="Spend by model">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-border">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Model</th>
                            <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wide text-muted-foreground">Calls</th>
                            <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wide text-muted-foreground">Tokens in</th>
                            <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wide text-muted-foreground">Tokens out</th>
                            <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wide text-muted-foreground">Cost</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($byModel as $row)
                            <tr wire:key="model-{{ $row['model'] }}">
                                <td class="px-3 py-2 font-mono text-xs text-foreground">{{ $row['model'] }}</td>
                                <td class="px-3 py-2 text-right text-muted-foreground">{{ number_format($row['calls']) }}</td>
                                <td class="px-3 py-2 text-right text-muted-foreground">{{ number_format($row['input_tokens']) }}</td>
                                <td class="px-3 py-2 text-right text-muted-foreground">{{ number_format($row['output_tokens']) }}</td>
                                <td class="px-3 py-2 text-right font-mono text-foreground">${{ number_format($row['cost_usd'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    @if($calls->total() === 0)
        <x-card><x-empty-state icon="circle-dollar-sign" title="No API calls logged yet" message="Costs appear here as documents are parsed. Re-parses that reuse a learned recipe run for free and never hit the API." /></x-card>
    @else
        <div class="overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
            <table class="w-full min-w-[56rem] text-sm">
                <thead class="border-b border-border bg-secondary/40">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">When</th>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Purpose</th>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Supplier / document</th>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Model</th>
                        <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wide text-muted-foreground">Tokens in / out</th>
                        <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wide text-muted-foreground">Cost</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($calls as $call)
                        <tr wire:key="call-{{ $call->uuid }}" class="hover:bg-accent/40">
                            <td class="px-3 py-2 whitespace-nowrap text-muted-foreground">{{ $call->created_at?->format('j M Y, H:i') }}</td>
                            <td class="px-3 py-2">
                                <x-badge color="gray">{{ str_replace('_', ' ', $call->purpose) }}</x-badge>
                            </td>
                            <td class="px-3 py-2">
                                @if($call->supplier_name)
                                    <div class="font-medium text-foreground">{{ $call->supplier_name }}</div>
                                @endif
                                @if($call->document_file)
                                    <div class="text-xs text-muted-foreground">{{ $call->document_file }}</div>
                                @endif
                                @if(! $call->supplier_name && ! $call->document_file)
                                    <span class="text-muted-foreground">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 font-mono text-xs text-muted-foreground">{{ $call->model }}</td>
                            <td class="px-3 py-2 text-right font-mono text-xs text-muted-foreground">{{ number_format($call->input_tokens) }} / {{ number_format($call->output_tokens) }}</td>
                            <td class="px-3 py-2 text-right font-mono text-foreground">${{ number_format((float) $call->cost_usd, 4) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div>{{ $calls->links() }}</div>
    @endif
</div>
