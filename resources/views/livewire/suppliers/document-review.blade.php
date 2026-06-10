<div class="space-y-6">
    <div>
        <a href="{{ route('suppliers.documents', $uuid) }}" wire:navigate class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
            <x-icon.chevron-right class="size-4 rotate-180" /> Back to documents
        </a>
        <h2 class="mt-2 font-serif text-2xl font-semibold">{{ $supplierName }} — review parsed wines</h2>
        @if($document)
            <p class="mt-1 text-sm text-muted-foreground">
                <x-badge :color="$document->status->getColour()">{{ $document->status->getLabel() }}</x-badge>
                <span class="ml-2 font-mono text-xs">{{ $document->file_name }}</span>
            </p>
            @if($document->analysis_notes)
                <p class="mt-2 text-sm text-muted-foreground">{{ $document->analysis_notes }}</p>
            @endif
        @endif
    </div>

    {{-- Preview banner: large PDFs only get a first-chunk preview until confirmed. --}}
    @if(str_contains((string) ($document?->analysis_notes), 'Preview'))
        <x-alert variant="warning">
            <div class="flex items-center justify-between gap-3">
                <span>This is a preview of the first pages. Run the full extraction to parse the whole document.</span>
                <x-button wire:click="runFull" wire:loading.attr="disabled" size="sm">Run full extraction</x-button>
            </div>
        </x-alert>
    @endif

    <div class="grid gap-4 md:grid-cols-3">
        {{-- Recipe / how we parsed it --}}
        <x-card title="Parse recipe" class="md:col-span-2">
            @if($profile)
                <dl class="space-y-1 text-sm">
                    <div class="flex gap-2"><dt class="text-muted-foreground">Mode:</dt><dd class="font-medium">{{ $profile->mode->value }}</dd></div>
                    <div class="flex gap-2"><dt class="text-muted-foreground">Model:</dt><dd class="font-mono text-xs">{{ $profile->model }}</dd></div>
                    <div class="flex gap-2"><dt class="text-muted-foreground">Confidence:</dt><dd>{{ $profile->confidence !== null ? round($profile->confidence * 100).'%' : '—' }}</dd></div>
                    @if(!empty($profile->recipe['mapping']))
                        <div><dt class="text-muted-foreground">Column mapping:</dt>
                            <dd class="mt-1 font-mono text-xs">{{ collect($profile->recipe['mapping'])->map(fn($h,$f) => "$f ← $h")->implode(' · ') }}</dd>
                        </div>
                    @endif
                    @if(!empty($profile->recipe['structure']))
                        <div><dt class="text-muted-foreground">Structure:</dt><dd class="mt-1 text-xs">{{ \Illuminate\Support\Str::limit($profile->recipe['structure'], 400) }}</dd></div>
                    @endif
                </dl>
                <p class="mt-2 text-xs text-muted-foreground">This recipe is reused (and refined) for this supplier's next upload.</p>
            @else
                <p class="text-sm text-muted-foreground">No recipe yet — analyse the document to learn one.</p>
            @endif
        </x-card>

        {{-- Actions --}}
        <x-card title="Actions">
            <div class="space-y-3">
                <div class="flex flex-wrap gap-2 text-xs">
                    <x-badge color="amber">{{ $counts['proposed'] ?? 0 }} proposed</x-badge>
                    <x-badge color="green">{{ $counts['approved'] ?? 0 }} approved</x-badge>
                    <x-badge color="gray">{{ $counts['rejected'] ?? 0 }} rejected</x-badge>
                </div>
                @if($canCommit)
                    <x-button wire:click="approveAll" wire:confirm="Add all proposed wines to your catalogue?" class="w-full" size="sm">Approve all proposed</x-button>
                @else
                    <p class="text-xs text-muted-foreground">This supplier's shared catalogue is managed centrally — parsed wines here are review-only.</p>
                @endif
                <x-button wire:click="saveRecipe" variant="outline" class="w-full" size="sm">Save corrections to recipe</x-button>
                <div>
                    <x-input.select name="model" label="Model (re-run)" :options="['claude-opus-4-8' => 'Opus 4.8 (best)', 'claude-sonnet-4-6' => 'Sonnet 4.6 (cheaper)']" wire:model="model" />
                    <x-button wire:click="reanalyse" variant="ghost" size="sm" class="mt-1">Re-analyse</x-button>
                </div>
            </div>
        </x-card>
    </div>

    {{-- Proposed wines --}}
    @if($wines->total() === 0)
        <x-card><x-empty-state icon="wine" title="No parsed wines yet" message="Once analysis completes, proposed wines appear here for review." /></x-card>
    @else
        <div class="overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
            <table class="w-full text-sm">
                <thead class="border-b border-border bg-secondary/40">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Wine</th>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Vintage</th>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Origin</th>
                        <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wide text-muted-foreground">Price</th>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Status</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($wines as $wine)
                        @php($p = $wine->payload)
                        @if($editingId === $wine->id)
                            <tr wire:key="edit-{{ $wine->id }}" class="bg-accent/40">
                                <td class="px-3 py-2" colspan="6">
                                    <div class="grid gap-2 sm:grid-cols-4">
                                        <x-input.text name="edit.wine_name" label="Wine" wire:model="edit.wine_name" />
                                        <x-input.text name="edit.producer" label="Producer" wire:model="edit.producer" />
                                        <x-input.text name="edit.vintage" label="Vintage" wire:model="edit.vintage" />
                                        <x-input.text name="edit.unit_price" label="Price" wire:model="edit.unit_price" />
                                        <x-input.select name="edit.colour" label="Colour" :options="collect($colours)->mapWithKeys(fn($c) => [$c->value => $c->getLabel()])->prepend('—', '')->all()" wire:model="edit.colour" />
                                        <x-input.text name="edit.country" label="Country" wire:model="edit.country" />
                                        <x-input.text name="edit.region" label="Region" wire:model="edit.region" />
                                        <x-input.text name="edit.grape" label="Grapes (comma)" wire:model="edit.grape" />
                                    </div>
                                    <div class="mt-2 flex gap-2">
                                        <x-button wire:click="saveEdit" size="sm">Save</x-button>
                                        <x-button wire:click="cancelEdit" variant="ghost" size="sm">Cancel</x-button>
                                    </div>
                                </td>
                            </tr>
                        @else
                            <tr wire:key="wine-{{ $wine->id }}" class="hover:bg-accent/40 {{ $wine->status->value !== 'proposed' ? 'opacity-60' : '' }}">
                                <td class="px-3 py-2.5">
                                    <p class="font-medium">{{ $p['wine_name'] ?? '—' }}</p>
                                    <p class="text-xs text-muted-foreground">{{ $p['producer'] ?? '' }}{{ !empty($p['grape']) ? ' · '.implode(', ', (array) $p['grape']) : '' }}</p>
                                    @if($wine->flag)<x-badge color="red" class="mt-1">{{ str_replace('_', ' ', $wine->flag) }}</x-badge>@endif
                                </td>
                                <td class="px-3 py-2.5 text-muted-foreground">{{ $p['vintage'] ?? 'NV' }}</td>
                                <td class="px-3 py-2.5 text-muted-foreground">{{ $p['country'] ?? '' }}@if(!empty($p['region']))<span class="text-xs"> · {{ $p['region'] }}</span>@endif</td>
                                <td class="px-3 py-2.5 text-right font-mono">{{ $p['unit_price'] ?? '—' }}</td>
                                <td class="px-3 py-2.5"><x-badge :color="$wine->status->getColour()">{{ $wine->status->getLabel() }}</x-badge></td>
                                <td class="px-3 py-2.5 text-right">
                                    @if($wine->status->value === 'proposed')
                                        <div class="flex items-center justify-end gap-1">
                                            @if($canCommit)
                                                <x-button wire:click="approve({{ $wine->id }})" size="sm" title="Approve"><x-icon.check class="size-4" /></x-button>
                                            @endif
                                            <x-button wire:click="startEdit({{ $wine->id }})" variant="ghost" size="sm" title="Edit"><x-icon.pencil class="size-4" /></x-button>
                                            <x-button wire:click="reject({{ $wine->id }})" variant="ghost" size="sm" class="text-destructive hover:bg-destructive/10" title="Reject"><x-icon.x class="size-4" /></x-button>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
        <div>{{ $wines->links() }}</div>
    @endif
</div>
