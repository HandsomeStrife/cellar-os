<div class="space-y-6">
    <div>
        <a href="{{ route('suppliers') }}" wire:navigate class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
            <x-icon.chevron-right class="size-4 rotate-180" /> Back to suppliers
        </a>
        <h2 class="mt-2 font-serif text-2xl font-semibold">{{ $supplierName }} — documents</h2>
        <p class="mt-1 text-sm text-muted-foreground">Upload this supplier's portfolio or price sheet and we'll prepare it for your catalogue.</p>
    </div>

    <x-card title="Upload a document">
        <form wire:submit="upload" class="space-y-4">
            <x-input.text name="docTitle" label="Title (optional)" wire:model="docTitle" placeholder="e.g. Spring 2026 price list" />
            <div>
                <x-input.label for="upload">File</x-input.label>
                <input type="file" id="upload" wire:model="upload"
                    class="mt-1 block w-full text-sm text-muted-foreground file:mr-4 file:rounded-md file:border-0 file:bg-primary file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-foreground hover:file:bg-primary/90" />
                <p class="mt-1 text-xs text-muted-foreground">CSV, Excel or PDF, up to 20MB.</p>
                <x-input.error :messages="$errors->get('upload')" />
            </div>
            <x-button type="submit" wire:loading.attr="disabled" wire:target="upload">
                <span wire:loading.remove wire:target="upload"><x-icon.upload class="size-4" /> Upload</span>
                <span wire:loading wire:target="upload">Uploading…</span>
            </x-button>
        </form>
    </x-card>

    @if($documents->isEmpty())
        <x-card><x-empty-state icon="file-text" title="No documents yet" message="Upload this supplier's price list above to get started." /></x-card>
    @else
        <div class="overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
            <table class="w-full text-sm">
                <thead class="border-b border-border bg-secondary/40">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Document</th>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Status</th>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Uploaded</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($documents as $document)
                        <tr wire:key="bdoc-{{ $document->id }}" class="hover:bg-accent/40">
                            <td class="px-3 py-2.5">
                                <p class="font-medium">{{ $document->title ?: $document->file_name }}</p>
                                <p class="font-mono text-xs text-muted-foreground">{{ $document->file_name }}</p>
                                @if($document->status === \Domain\Supplier\Enums\SupplierDocumentStatus::Failed && $document->analysis_notes)
                                    <p class="mt-1 text-xs text-destructive">{{ $document->analysis_notes }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-2.5"><x-badge :color="$document->status->getColour()">{{ $document->status->getLabel() }}</x-badge></td>
                            <td class="px-3 py-2.5 text-muted-foreground">{{ $document->created_at?->format('j M Y') }}</td>
                            <td class="px-3 py-2.5 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <x-button wire:click="analyse({{ $document->id }})" variant="outline" size="sm">Analyse</x-button>
                                    <x-button :href="route('suppliers.documents.download', $document->id)" variant="ghost" size="sm" aria-label="Download"><x-icon.download class="size-4" /></x-button>
                                    <x-button wire:click="delete({{ $document->id }})" wire:confirm="Remove this document?" variant="ghost" size="sm" class="text-destructive hover:bg-destructive/10" aria-label="Delete"><x-icon.trash-2 class="size-4" /></x-button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
