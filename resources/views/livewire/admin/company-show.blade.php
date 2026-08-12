<div class="space-y-6">
    <div>
        <a href="{{ route('admin.companies') }}" wire:navigate class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
            <x-icon.chevron-right class="size-4 rotate-180" /> Back to companies
        </a>
        <h2 class="mt-2 font-serif text-2xl font-semibold">{{ $company?->name }}</h2>
    </div>

    {{-- Plan & commercial terms --}}
    <x-card title="Plan & billing" subtitle="What they can do, and what we charge for it. Saved together.">

        @if($company)
            <div class="mb-5 space-y-2 text-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <x-badge color="wine">{{ $company->plan->getLabel() }}</x-badge>
                    <x-badge :color="$company->billing_arrangement->getColour()">{{ $company->billing_arrangement->getLabel() }}</x-badge>
                    <span class="font-mono text-muted-foreground">{{ $company->billingLabel() }}</span>

                    @if($company->onTrial())
                        <x-badge color="amber">Trial &middot; {{ $company->trialDaysRemaining() }} {{ $company->trialDaysRemaining() === 1 ? 'day' : 'days' }} left</x-badge>
                    @elseif($company->trialLapsed())
                        <x-badge color="red">Trial ended {{ $company->trial_ends_at->format('j M Y') }}, not subscribed</x-badge>
                    @endif
                </div>

                @if($company->entitlementReduced())
                    <p class="text-destructive">
                        Getting {{ $company->effectivePlan()->getLabel() }} only.
                        @if($company->has_active_subscription)
                            Their trial of {{ $company->plan->getLabel() }} ended; they're back to what their subscription pays for.
                        @else
                            The trial ended with no subscription behind it.
                        @endif
                    </p>
                @elseif($company->trialLapsed())
                    {{-- The honest version of "trial ended": on the entry tier there is
                         nothing below to fall back to, so nothing was actually revoked. --}}
                    <p class="text-muted-foreground">Their trial has ended and they never subscribed, but {{ $company->plan->getLabel() }} is the entry tier so they keep full access. Worth a conversation.</p>
                @endif

                @if(! $company->billing_arrangement->dependsOnStripe() && $company->has_active_subscription)
                    <p class="text-destructive">Stripe is still billing this company. Cancel the subscription in Stripe, or these terms are only true on this screen.</p>
                @endif
            </div>
        @endif

        <form wire:submit="saveBilling" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input.select name="plan" label="Plan" hint="What the company can do." wire:model="plan" :options="$plans" />
                <x-input.select name="arrangement" label="Arrangement" hint="What we charge for it." wire:model.live="arrangement" :options="$arrangements" />
            </div>

            @if($arrangement === \Domain\Billing\Enums\BillingArrangement::Custom->value)
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-input.text name="customPrice" label="Agreed price" placeholder="49.50" wire:model="customPrice" inputmode="decimal" />
                    <x-input.select name="customCurrency" label="Currency" wire:model="customCurrency" :options="$currencies" />
                    <x-input.select name="customInterval" label="Billed" wire:model="customInterval" :options="$intervals" />
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input.text name="trialEndsAt" label="Trial ends" type="date" wire:model="trialEndsAt" hint="Leave blank for no trial." />
                    <p class="mt-1.5 text-xs text-muted-foreground">
                        When it ends, they drop to {{ \Domain\Billing\Enums\Plan::default()->getLabel() }} unless they subscribe
                        @if($plan === \Domain\Billing\Enums\Plan::default()->value)
                            &mdash; which is what they're already on, so this trial won't change what they can do.
                        @endif
                    </p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <x-button type="button" size="sm" variant="outline" wire:click="grantTrial(14)">14 days</x-button>
                        <x-button type="button" size="sm" variant="outline" wire:click="grantTrial(30)">30 days</x-button>
                        <x-button type="button" size="sm" variant="outline" wire:click="grantTrial(90)">3 months</x-button>
                        @if($trialEndsAt !== '')
                            <x-button type="button" size="sm" variant="ghost" wire:click="endTrialNow">End it now</x-button>
                            <x-button type="button" size="sm" variant="ghost" wire:click="removeTrial">Remove trial</x-button>
                        @endif
                    </div>
                </div>
                <x-input.textarea name="billingNotes" label="Notes" rows="4" wire:model="billingNotes" hint="Why these terms, and who agreed them." />
            </div>

            <x-button type="submit">Save billing</x-button>
        </form>
    </x-card>

    {{-- Team --}}
    <x-card title="Team">
        @if($users->isEmpty())
            <p class="text-sm text-muted-foreground">No users yet.</p>
        @else
            <div class="overflow-x-auto rounded-lg border border-border">
                <table class="w-full text-sm">
                    <thead class="border-b border-border bg-secondary/40">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Name</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Email</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Role</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Access</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($users as $member)
                            <tr wire:key="cuser-{{ $member->id }}" class="hover:bg-accent/40">
                                <td class="px-3 py-2.5 font-medium">{{ $member->full_name ?: '—' }}</td>
                                <td class="px-3 py-2.5 text-muted-foreground">{{ $member->email }}</td>
                                <td class="px-3 py-2.5"><x-badge color="gray">{{ $member->role->getLabel() }}</x-badge></td>
                                <td class="px-3 py-2.5">
                                    @if($member->has_password)
                                        <x-badge color="green">Active</x-badge>
                                    @else
                                        <x-badge color="amber">Invite pending</x-badge>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <x-dropdown :label="'Actions for '.$member->email">
                                        <x-dropdown.item icon="eye" :post="route('admin.impersonate.user', $member->id)">View the app as this user</x-dropdown.item>
                                        <x-dropdown.item icon="mail" wire:click="resendInvite({{ $member->id }})">Resend invite</x-dropdown.item>
                                        <x-dropdown.divider />
                                        <x-dropdown.item icon="trash-2" variant="danger" wire:click="removeUser({{ $member->id }})" wire:confirm="Remove {{ $member->email }}?">Remove from company</x-dropdown.item>
                                    </x-dropdown>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <form wire:submit="addUser" class="mt-5 border-t border-border pt-5">
            <p class="mb-3 text-sm font-medium">Add a user</p>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-input.text name="newUserName" label="Name" wire:model="newUserName" required />
                <x-input.email name="newUserEmail" label="Email" wire:model="newUserEmail" required />
                <x-input.select name="newUserRole" label="Role" wire:model="newUserRole" :options="$roles" />
            </div>
            <div class="mt-4">
                <x-button type="submit"><x-icon.mail class="size-4" /> Add user &amp; send invite</x-button>
            </div>
        </form>
    </x-card>

    {{-- Danger zone --}}
    <x-card title="Danger zone">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-muted-foreground">Permanently delete this company, its users, venues, inventory and orders. Cancels any active subscription. This cannot be undone.</p>
            <x-button wire:click="deleteCompany" wire:confirm="Delete {{ $company?->name }} and ALL its data? This cannot be undone." variant="danger" size="sm">
                <x-icon.trash-2 class="size-4" /> Delete company
            </x-button>
        </div>
    </x-card>

    {{-- Venues --}}
    <x-card title="Venues">
        @if($venues->isEmpty())
            <p class="text-sm text-muted-foreground">No venues yet.</p>
        @else
            <ul class="divide-y divide-border">
                @foreach($venues as $venue)
                    <li class="flex items-center justify-between gap-3 py-2.5">
                        <span class="font-medium">{{ $venue->name }}</span>
                        <span class="text-sm text-muted-foreground">{{ collect([$venue->city, $venue->country])->filter()->implode(', ') ?: '—' }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</div>
