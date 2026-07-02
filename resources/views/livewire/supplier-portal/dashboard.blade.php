<div class="space-y-6">
    <div>
        <h2 class="font-serif text-2xl font-semibold">Welcome{{ $supplierUser?->name ? ', '.$supplierUser->name : '' }}</h2>
        <p class="mt-1 text-sm text-muted-foreground">
            {{ $supplier?->name }}. Upload your portfolios and price sheets, and we'll prepare them for the CellarOS catalogue.
        </p>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat label="Documents" :value="$documents->count()" icon="file-text" />
        <x-stat label="Awaiting analysis" :value="$awaitingCount" icon="clock" />
        <x-stat label="Analysed" :value="$analysedCount" icon="circle-check" />
    </div>

    {{-- Title and button describe the same act — the upload itself happens in Documents. --}}
    <x-card title="Send us your price list">
        <p class="text-sm text-muted-foreground">
            Upload your portfolio or price list in whatever format you have (CSV, Excel or PDF) from the Documents area.
            We'll review it and extract the wines into a standard format.
        </p>
        <div class="mt-4">
            <x-button :href="route('supplier.documents')" wire:navigate>
                <x-icon.upload class="size-4" /> Upload in Documents
            </x-button>
        </div>
    </x-card>

    @if($documents->isNotEmpty())
        <x-card title="Recent documents">
            <ul class="divide-y divide-border">
                @foreach($documents->take(5) as $document)
                    <li class="flex items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="truncate font-medium">{{ $document->title ?: $document->file_name }}</p>
                            <p class="text-xs text-muted-foreground">{{ $document->created_at?->format('j M Y') }}</p>
                        </div>
                        <x-badge :color="$document->status->getColour()">{{ $document->status->getLabel() }}</x-badge>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif
</div>
