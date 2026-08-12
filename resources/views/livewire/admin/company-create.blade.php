<div class="space-y-6">
    <div>
        <a href="{{ route('admin.companies') }}" wire:navigate class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
            <x-icon.chevron-right class="size-4 rotate-180" /> Back to companies
        </a>
        <h2 class="mt-2 font-serif text-2xl font-semibold">New company</h2>
    </div>

    <form wire:submit="create" class="space-y-6">
        <x-card title="The company">
            <div class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input.text name="name" label="Company name" wire:model="name" required autofocus />
                    <x-input.select name="baseCurrency" label="Base currency" wire:model="baseCurrency" :options="$currencies" />
                </div>
                <x-input.text name="venueName" label="First venue" wire:model="venueName"
                              hint="Every company needs somewhere to hold stock. Left blank, it takes the company name." />
            </div>
        </x-card>

        <x-card title="Plan & billing" subtitle="The plan decides what they can do; the arrangement decides what we charge.">
            <div class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input.select name="plan" label="Plan" wire:model="plan" :options="$plans" />
                    <x-input.select name="arrangement" label="Arrangement" wire:model.live="arrangement" :options="$arrangements" />
                </div>

                @if($arrangement === 'custom')
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-input.text name="customPrice" label="Agreed price" placeholder="49.50" wire:model="customPrice" inputmode="decimal" />
                        <x-input.select name="customCurrency" label="Currency" wire:model="customCurrency" :options="$currencies" />
                        <x-input.select name="customInterval" label="Billed" wire:model="customInterval" :options="$intervals" />
                    </div>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input.text name="trialDays" label="Trial length (days)" type="number" min="0" max="730" wire:model="trialDays"
                                  hint="Leave blank for no trial. 30 is a month, 90 a quarter." />
                    <x-input.textarea name="billingNotes" label="Notes" rows="3" wire:model="billingNotes"
                                      hint="Why these terms, and who agreed them." />
                </div>
            </div>
        </x-card>

        <x-card title="Owner" subtitle="Optional. You can add people later from the company's page.">
            <div class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input.text name="ownerName" label="Name" wire:model="ownerName" />
                    <x-input.email name="ownerEmail" label="Email" wire:model="ownerEmail" />
                </div>
                <x-input.checkbox name="sendInvite" label="Email them a link to set their password" wire:model="sendInvite" />
            </div>
        </x-card>

        <div class="flex items-center gap-3">
            <x-button type="submit">Create company</x-button>
            <x-button href="{{ route('admin.companies') }}" variant="ghost">Cancel</x-button>
        </div>
    </form>
</div>
