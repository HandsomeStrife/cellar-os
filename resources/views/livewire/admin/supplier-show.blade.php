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
                    Onboarded — this supplier self-manages via the portal.
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

    {{-- Documents --}}
    <x-card title="Portfolios & price sheets">
        @if($documents->isEmpty())
            <x-empty-state icon="file-text" title="No documents" message="This supplier hasn't uploaded any documents yet." />
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
                                    @if($document->status === \Domain\Supplier\Enums\SupplierDocumentStatus::Failed && $document->analysis_notes)
                                        <p class="mt-1 text-xs text-destructive">{{ $document->analysis_notes }}</p>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5"><x-badge :color="$document->status->getColour()">{{ $document->status->getLabel() }}</x-badge></td>
                                <td class="px-3 py-2.5 text-muted-foreground">{{ $document->created_at?->format('j M Y') }}</td>
                                <td class="px-3 py-2.5 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <x-button wire:click="analyse({{ $document->id }})" variant="outline" size="sm">Analyse</x-button>
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
