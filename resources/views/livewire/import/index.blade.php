<div class="space-y-6">
    @if(! $entitled)
        <x-upgrade-gate
            title="Importing price lists is a paid feature"
            message="Upload a supplier's CSV or Excel price list, map the columns once, and build your catalogue in seconds."
            plan="Starter"
        />
    @else
        <x-page-header title="Import a price list" subtitle="Upload a supplier's CSV or Excel file and map the columns once." />

        {{-- Stepper --}}
        @php($steps = ['Upload', 'Map columns', 'Preview', 'Import'])
        <ol class="flex flex-wrap items-center gap-2 text-sm">
            @foreach($steps as $i => $label)
                @php($n = $i + 1)
                <li class="flex items-center gap-2">
                    <span @class([
                        'flex size-6 items-center justify-center rounded-full text-xs font-semibold',
                        'bg-primary text-primary-foreground' => $step >= $n,
                        'bg-secondary text-muted-foreground' => $step < $n,
                    ])>{{ $n }}</span>
                    <span class="{{ $step >= $n ? 'text-foreground' : 'text-muted-foreground' }}">{{ $label }}</span>
                    @if(! $loop->last)
                        <x-icon.chevron-right class="size-4 text-muted-foreground" />
                    @endif
                </li>
            @endforeach
        </ol>

        {{-- Step 1: Upload --}}
        @if($step === 1)
            <x-card title="Upload a price list" subtitle="CSV or Excel (.csv, .xls, .xlsx), up to 10 MB.">
                @if($suppliers->isEmpty())
                    <x-alert variant="info">
                        You need a supplier first.
                        <a href="{{ route('suppliers') }}" class="font-medium underline" wire:navigate>Add a supplier</a> to import their list.
                    </x-alert>
                @else
                    <form wire:submit="uploadFile" class="space-y-4">
                        <x-input.select
                            name="supplierId"
                            label="Supplier"
                            :options="$suppliers->pluck('name', 'id')->all()"
                            placeholder="Select a supplier"
                            wire:model="supplierId"
                        />

                        <div x-data="{ dragging: false }">
                            <x-input.label for="upload-file">Price-list file</x-input.label>
                            {{-- Dropzone: the label wraps a hidden file input, so click-to-browse
                                 and drag-and-drop both feed the same wire:model. --}}
                            <label
                                for="upload-file"
                                x-on:dragover.prevent="dragging = true"
                                x-on:dragleave.prevent="dragging = false"
                                x-on:drop.prevent="dragging = false; $refs.file.files = $event.dataTransfer.files; $refs.file.dispatchEvent(new Event('change'))"
                                x-bind:class="dragging ? 'border-ring bg-accent/40' : 'border-input'"
                                class="mt-1.5 flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed bg-card px-6 py-10 text-center transition hover:bg-accent/30"
                            >
                                <x-icon.upload class="size-6 text-muted-foreground" />
                                @if($upload)
                                    <p class="text-sm font-medium text-foreground">{{ $upload->getClientOriginalName() }}</p>
                                    <p class="text-xs text-muted-foreground">Choose a different file, or continue below.</p>
                                @else
                                    <p class="text-sm font-medium text-foreground">Drop your price list here, or click to browse</p>
                                    <p class="text-xs text-muted-foreground">CSV or Excel, up to 10 MB</p>
                                @endif
                                <input id="upload-file" type="file" x-ref="file" wire:model="upload" accept=".csv,.txt,.xls,.xlsx" class="sr-only" />
                            </label>
                            <x-input.error :messages="$errors->get('upload')" />
                            <div wire:loading wire:target="upload" class="mt-1 text-xs text-muted-foreground">Reading file…</div>
                        </div>

                        <div class="flex justify-end">
                            <x-button type="submit" wire:loading.attr="disabled" wire:target="uploadFile,upload">
                                Upload &amp; continue
                            </x-button>
                        </div>
                    </form>
                @endif
            </x-card>
        @endif

        {{-- Step 2: Map columns --}}
        @if($step === 2)
            <x-card title="Map columns" subtitle="Match each product field to a column from your file. We've guessed where we can.">
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach($fields as $field => $label)
                        <div>
                            <x-input.label>
                                {{ $label }}
                                @if($field === 'wine_name')<span class="text-destructive">*</span>@endif
                            </x-input.label>
                            <select
                                wire:model="mapping.{{ $field }}"
                                class="select-field mt-1.5 block w-full rounded-md border border-input bg-card px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-2 focus:ring-ring/40"
                            >
                                <option value="">– skip –</option>
                                @foreach($headers as $header)
                                    <option value="{{ $header }}">{{ $header }}</option>
                                @endforeach
                            </select>
                            @if($field === 'wine_name')
                                <x-input.error :messages="$errors->get('mapping.wine_name')" />
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="mt-5 flex items-center justify-between">
                    <x-button wire:click="restart" variant="ghost">Start over</x-button>
                    <x-button wire:click="toPreview">Preview</x-button>
                </div>
            </x-card>
        @endif

        {{-- Step 3: Preview --}}
        @if($step === 3)
            <x-card title="Preview" subtitle="A sample of how your wines will be imported.">
                @if($preview === [])
                    <p class="py-6 text-center text-sm text-muted-foreground">Nothing to preview, check your column mapping.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th class="py-2 pr-3">Wine</th>
                                    <th class="py-2 pr-3">Producer</th>
                                    <th class="py-2 pr-3">Origin</th>
                                    <th class="py-2 pr-3">Colour</th>
                                    <th class="py-2 pr-3">Vintage</th>
                                    <th class="py-2 pr-3 text-right">Price</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach($preview as $p)
                                    <tr>
                                        <td class="py-2 pr-3 font-medium text-foreground">{{ $p->wine_name }}</td>
                                        <td class="py-2 pr-3 text-muted-foreground">{{ $p->producer ?? '–' }}</td>
                                        <td class="py-2 pr-3 text-muted-foreground">{{ $p->country ?? '–' }}</td>
                                        <td class="py-2 pr-3 text-muted-foreground">{{ $p->colour?->getLabel() ?? '–' }}</td>
                                        <td class="py-2 pr-3 text-muted-foreground">{{ $p->vintage ?? 'NV' }}</td>
                                        <td class="py-2 pr-3 text-right tabular-nums">{{ $p->unit_price !== null ? '£'.number_format((float) $p->unit_price, 2) : '–' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <div class="mt-5 flex items-center justify-between">
                    <x-button wire:click="$set('step', 2)" variant="ghost">Back</x-button>
                    <x-button wire:click="runImport" wire:loading.attr="disabled" wire:target="runImport">
                        <span wire:loading.remove wire:target="runImport">Import wines</span>
                        <span wire:loading wire:target="runImport">Importing…</span>
                    </x-button>
                </div>
            </x-card>
        @endif

        {{-- Step 4: Done --}}
        @if($step === 4)
            <x-card>
                <div class="flex flex-col items-center justify-center gap-3 py-8 text-center">
                    <span class="flex size-12 items-center justify-center rounded-full bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300">
                        <x-icon.circle-check class="size-6" />
                    </span>
                    <div>
                        <p class="font-serif text-xl font-semibold">Import complete</p>
                        <p class="mt-1 text-sm text-muted-foreground">{{ number_format((int) $importedCount) }} wines added to your catalogue.</p>
                    </div>
                    <div class="flex gap-2">
                        <x-button :href="route('catalogue')" wire:navigate>View catalogue</x-button>
                        <x-button wire:click="restart" variant="outline">Import another</x-button>
                    </div>
                </div>
            </x-card>
        @endif
    @endif
</div>
