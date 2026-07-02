<div class="space-y-6">
    <div class="flex items-center gap-3">
        <label for="status-filter" class="text-sm text-muted-foreground">Filter</label>
        <select id="status-filter" wire:model.live="status" class="select-field rounded-md border border-input bg-card px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-2 focus:ring-ring/40">
            <option value="">All statuses</option>
            @foreach($statuses as $s)
                <option value="{{ $s->value }}">{{ $s->label() }}</option>
            @endforeach
        </select>
    </div>

    @if($enquiries->total() === 0)
        <x-card><x-empty-state icon="mail" title="No enquiries" message="Contact form submissions will appear here." /></x-card>
    @else
        <div class="overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
            <table class="w-full min-w-[48rem] text-sm">
                <thead class="border-b border-border bg-secondary/40">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">From</th>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Message</th>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Status</th>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Received</th>
                        <th class="px-3 py-2"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($enquiries as $enquiry)
                        <tr wire:key="enquiry-{{ $enquiry->uuid }}" class="align-top hover:bg-accent/40">
                            <td class="px-3 py-3">
                                <div class="font-medium text-foreground">{{ $enquiry->name }}</div>
                                <a href="mailto:{{ $enquiry->email }}" class="text-primary hover:underline">{{ $enquiry->email }}</a>
                                @if($enquiry->company)
                                    <div class="text-xs text-muted-foreground">{{ $enquiry->company }}</div>
                                @endif
                            </td>
                            <td class="max-w-md px-3 py-3 text-muted-foreground">
                                <p class="whitespace-pre-line">{{ $enquiry->message }}</p>
                            </td>
                            <td class="px-3 py-3">
                                <x-badge :color="$enquiry->status->color()">{{ $enquiry->status->label() }}</x-badge>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-muted-foreground">{{ $enquiry->created_at?->format('j M Y, H:i') }}</td>
                            <td class="px-3 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <select wire:change="mark('{{ $enquiry->uuid }}', $event.target.value)" aria-label="Set status" class="select-field rounded-md border border-input bg-card px-2 py-1 text-xs shadow-sm focus:outline-none focus:ring-2 focus:ring-ring/40">
                                        @foreach($statuses as $s)
                                            <option value="{{ $s->value }}" @selected($s === $enquiry->status)>{{ $s->label() }}</option>
                                        @endforeach
                                    </select>
                                    <x-button wire:click="deleteEnquiry('{{ $enquiry->uuid }}')" wire:confirm="Delete this enquiry? This cannot be undone." variant="ghost" size="sm" class="text-destructive hover:bg-destructive/10">
                                        <x-icon.trash-2 class="size-4" />
                                    </x-button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div>{{ $enquiries->links() }}</div>
    @endif
</div>
