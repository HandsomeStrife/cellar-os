<div class="space-y-6">
    <div>
        <a href="{{ route('admin.suppliers') }}" wire:navigate class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
            <x-icon.chevron-right class="size-4 rotate-180" /> Back to suppliers
        </a>
        <div class="mt-2 flex flex-wrap items-center gap-3">
            <h2 class="font-serif text-2xl font-semibold">{{ $supplier?->name }}</h2>
            @if($supplier?->tier)<x-badge :color="$supplier->tier->getColour()">{{ $supplier->tier->getLabel() }}</x-badge>@endif
        </div>
    </div>

    {{-- Tier / onboarding --}}
    <x-card title="Listing & onboarding">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-muted-foreground">
                @if($supplier?->tier === \Domain\Supplier\Enums\SupplierTier::Private)
                    Private to the company that added it. Make it public to let every company discover and connect to it.
                @elseif($supplier?->tier === \Domain\Supplier\Enums\SupplierTier::Listed)
                    Listed publicly. Mark it onboarded once it manages its own portal account.
                @else
                    Onboarded: this supplier self-manages via the portal.
                @endif
            </p>
            <div class="flex gap-2">
                @if($supplier?->tier === \Domain\Supplier\Enums\SupplierTier::Private)
                    <x-button wire:click="makePublic" variant="outline" size="sm">Make public</x-button>
                @endif
                @if($supplier?->tier !== \Domain\Supplier\Enums\SupplierTier::Onboarded)
                    <x-button wire:click="markOnboarded" size="sm">Mark onboarded</x-button>
                @endif
            </div>
        </div>
    </x-card>

    {{-- Company profile --}}
    <x-card title="Company profile">
        <form wire:submit="saveProfile" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input.text name="name" label="Company name" wire:model="name" required />
                <x-input.select name="status" label="Status" :options="$statuses" wire:model="status" />
                <x-input.text name="contact" label="Contact name" wire:model="contact" />
                <x-input.email name="email" label="Email" wire:model="email" />
                <x-input.text name="phone" label="Phone" wire:model="phone" />
                <x-input.text name="website" label="Website" wire:model="website" />
                <x-input.text name="address" label="Address" wire:model="address" />
                <x-input.text name="city" label="City" wire:model="city" />
                <x-input.text name="postcode" label="Postcode" wire:model="postcode" />
                <x-input.text name="country" label="Country" wire:model="country" />
                <x-input.text name="location" label="Location (display)" wire:model="location" />
            </div>
            <x-button type="submit">Save profile</x-button>
        </form>
    </x-card>

    {{-- Portal users --}}
    <x-card title="Portal users">
        @if($users->isEmpty())
            <p class="text-sm text-muted-foreground">No users yet. Add one below to send them a portal invite.</p>
        @else
            <div class="overflow-x-auto rounded-lg border border-border">
                <table class="w-full text-sm">
                    <thead class="border-b border-border bg-secondary/40">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Name</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Email</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Access</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($users as $user)
                            <tr wire:key="suser-{{ $user->id }}" class="hover:bg-accent/40">
                                <td class="px-3 py-2.5 font-medium">{{ $user->name }}</td>
                                <td class="px-3 py-2.5 text-muted-foreground">{{ $user->email }}</td>
                                <td class="px-3 py-2.5">
                                    @if($user->has_password)
                                        <x-badge color="green">Active</x-badge>
                                    @else
                                        <x-badge color="amber">Invite pending</x-badge>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <form method="POST" action="{{ route('admin.impersonate.supplier-user', $user->id) }}">
                                            @csrf
                                            <x-button type="submit" variant="ghost" size="sm" aria-label="Impersonate {{ $user->email }}" title="View the portal as this user">
                                                <x-icon.eye class="size-4" />
                                            </x-button>
                                        </form>
                                        <x-button wire:click="resendInvite({{ $user->id }})" variant="ghost" size="sm" aria-label="Resend invite">
                                            <x-icon.mail class="size-4" />
                                        </x-button>
                                        <x-button wire:click="deleteUser({{ $user->id }})" wire:confirm="Remove {{ $user->email }}?" variant="ghost" size="sm" class="text-destructive hover:bg-destructive/10" aria-label="Delete user">
                                            <x-icon.trash-2 class="size-4" />
                                        </x-button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <form wire:submit="addUser" class="mt-5 border-t border-border pt-5">
            <p class="mb-3 text-sm font-medium">Add a user</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input.text name="newUserName" label="Name" wire:model="newUserName" required />
                <x-input.email name="newUserEmail" label="Email" wire:model="newUserEmail" required />
            </div>
            <div class="mt-4">
                <x-button type="submit">
                    <x-icon.mail class="size-4" /> Add user &amp; send invite
                </x-button>
            </div>
        </form>
    </x-card>

    {{-- CRM notes — relationship log, list-access intel, chase-ups. Admin-only. --}}
    <x-card title="Notes" subtitle="Relationship log, visible to admins only.">
        <form wire:submit="addNote" class="flex items-start gap-2">
            <div class="grow">
                <x-input.textarea name="newNote" wire:model="newNote" rows="2" placeholder="e.g. Spoke to sales — priced list arrives quarterly by email; chase in September." />
            </div>
            <x-button type="submit" size="sm">Add note</x-button>
        </form>

        @if($notes->isNotEmpty())
            <ul class="mt-4 space-y-3">
                @foreach($notes as $note)
                    <li wire:key="note-{{ $note->id }}" class="flex items-start justify-between gap-3 rounded-md border border-border bg-secondary/30 p-3">
                        <div>
                            <p class="whitespace-pre-line text-sm">{{ $note->note }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ $note->created_at?->format('j M Y, H:i') }}</p>
                        </div>
                        <x-button wire:click="deleteNote({{ $note->id }})" wire:confirm="Remove this note?" variant="ghost" size="sm" class="text-destructive hover:bg-destructive/10" aria-label="Delete note">
                            <x-icon.trash-2 class="size-4" />
                        </x-button>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    {{-- Documents --}}
    <x-card title="Portfolios & price sheets">
        {{-- How we import this supplier: the learned recipe + AI spend to date --}}
        @if(! empty($parseProfiles) || ($aiSpend['calls'] ?? 0) > 0)
            <div class="mb-4 grid gap-3 sm:grid-cols-2">
                @foreach($parseProfiles as $mode => $profile)
                    @php($strategy = $profile->recipe['strategy'] ?? (isset($profile->recipe['mapping']) ? 'tabular' : 'llm'))
                    <div class="rounded-lg border border-border bg-secondary/30 px-3 py-2 text-sm">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">How we import ({{ $mode }})</p>
                        <p class="mt-1 text-foreground">
                            @switch($strategy)
                                @case('pattern') Deterministic pattern rules; re-imports are free @break
                                @case('tabular') Learned column mapping; re-imports are free @break
                                @default LLM extraction; re-imports re-bill tokens
                            @endswitch
                        </p>
                        <p class="mt-0.5 text-xs text-muted-foreground">Confidence {{ number_format(($profile->confidence ?? 0) * 100) }}%{{ $profile->model ? ' · '.$profile->model : '' }}</p>
                    </div>
                @endforeach
                @if(($aiSpend['calls'] ?? 0) > 0)
                    <div class="rounded-lg border border-border bg-secondary/30 px-3 py-2 text-sm">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">AI spend to date</p>
                        <p class="mt-1 font-mono text-foreground">${{ number_format($aiSpend['cost_usd'], 4) }}</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">across {{ $aiSpend['calls'] }} call(s) · <a href="{{ route('admin.costs') }}" class="text-primary hover:underline">full ledger</a></p>
                    </div>
                @endif
            </div>
        @endif

        {{-- Admin upload: register a portfolio / price sheet directly, no portal
             account needed. A published URL enrols it in the weekly refresh. --}}
        <form wire:submit="uploadDocument" class="mb-4 rounded-lg border border-border bg-secondary/30 p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Upload a document</p>
            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                <x-input.text name="docTitle" label="Title (optional)" wire:model="docTitle" placeholder="e.g. Spring 2026 trade list" />
                <x-input.text name="docSourceUrl" label="Published URL (optional)" wire:model="docSourceUrl" placeholder="https://…" hint="If the supplier publishes this list at a fixed URL, the weekly refresh will pick up new editions." />
            </div>
            <div class="mt-4 flex flex-wrap items-end gap-4">
                <div class="min-w-64 flex-1">
                    <x-input.label for="docUpload">File</x-input.label>
                    <input type="file" id="docUpload" wire:model="docUpload"
                        class="mt-1 block w-full text-sm text-muted-foreground file:mr-4 file:rounded-md file:border-0 file:bg-primary file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-foreground hover:file:bg-primary/90" />
                    <p class="mt-1 text-xs text-muted-foreground">CSV, Excel or PDF, up to 20MB.</p>
                    <x-input.error :messages="$errors->get('docUpload')" />
                </div>
                <x-button type="submit" wire:loading.attr="disabled" wire:target="docUpload, uploadDocument">
                    <span wire:loading.remove wire:target="docUpload, uploadDocument"><x-icon.upload class="size-4" /> Upload</span>
                    <span wire:loading wire:target="docUpload, uploadDocument">Uploading…</span>
                </x-button>
            </div>
        </form>

        @if($documents->isEmpty())
            <x-empty-state icon="file-text" title="No documents" message="Upload this supplier's price list above to get started." />
        @else
            <div class="overflow-x-auto rounded-lg border border-border">
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
                            <tr wire:key="adoc-{{ $document->id }}" class="hover:bg-accent/40">
                                <td class="px-3 py-2.5">
                                    <p class="font-medium">{{ $document->title ?: $document->file_name }}</p>
                                    <p class="font-mono text-xs text-muted-foreground">{{ $document->file_name }}</p>
                                    @if($document->analysis_notes)
                                        <p class="mt-1 whitespace-pre-line text-xs {{ $document->status === \Domain\Supplier\Enums\SupplierDocumentStatus::Failed ? 'text-destructive' : 'text-muted-foreground' }}">{{ $document->analysis_notes }}</p>
                                    @endif
                                    @php($pc = $parsedCounts[$document->id] ?? [])
                                    @if(($pc['proposed'] ?? 0) + ($pc['approved'] ?? 0) > 0)
                                        <p class="mt-1 text-xs text-muted-foreground">{{ $pc['proposed'] ?? 0 }} proposed · {{ $pc['approved'] ?? 0 }} approved</p>
                                    @endif
                                    @if($document->analysed_at)
                                        <p class="mt-0.5 text-xs text-muted-foreground">Last analysed {{ $document->analysed_at->format('j M Y, H:i') }}</p>
                                    @endif
                                    @if($document->archived_at)
                                        <p class="mt-0.5 text-xs text-amber-600">Archived (superseded) {{ $document->archived_at->format('j M Y') }}</p>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5"><x-badge :color="$document->status->getColour()">{{ $document->status->getLabel() }}</x-badge></td>
                                <td class="px-3 py-2.5 text-muted-foreground">{{ $document->created_at?->format('j M Y') }}</td>
                                <td class="px-3 py-2.5 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <x-button wire:click="analyse({{ $document->id }})" variant="outline" size="sm">Analyse</x-button>
                                        @if(($pc['proposed'] ?? 0) > 0)
                                            <x-button wire:click="approveDocument({{ $document->id }})" wire:confirm="Add all proposed wines to the catalogue?" size="sm">Approve all</x-button>
                                        @endif
                                        <x-button :href="route('admin.supplier-documents.download', $document->id)" variant="ghost" size="sm" aria-label="Download">
                                            <x-icon.download class="size-4" />
                                        </x-button>
                                        <x-button wire:click="deleteDocument({{ $document->id }})" wire:confirm="Remove this document?" variant="ghost" size="sm" class="text-destructive hover:bg-destructive/10" aria-label="Delete">
                                            <x-icon.trash-2 class="size-4" />
                                        </x-button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</div>
