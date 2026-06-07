<div class="space-y-8">
    <div class="text-center">
        <h2 class="font-serif text-3xl font-semibold">Plans &amp; pricing</h2>
        <p class="mt-2 text-muted-foreground">Upgrade as your wine programme grows. Cancel anytime.</p>
        @if(! $billingConfigured)
            <div class="mx-auto mt-4 max-w-xl">
                <x-alert variant="info">Card payments aren't enabled in this environment yet — choosing a plan will let you know.</x-alert>
            </div>
        @endif
    </div>

    <div class="grid gap-5 lg:grid-cols-4">
        @foreach($plans as $plan)
            @php($isCurrent = $plan === $currentPlan)
            <div @class([
                'flex flex-col rounded-lg border bg-card p-6 shadow-sm',
                'border-primary ring-1 ring-primary' => $isCurrent,
                'border-border' => ! $isCurrent,
            ])>
                <div class="flex items-center justify-between">
                    <h3 class="font-serif text-xl font-semibold">{{ $plan->getLabel() }}</h3>
                    @if($isCurrent)
                        <x-badge color="wine">Current</x-badge>
                    @endif
                </div>

                <p class="mt-3">
                    <span class="font-serif text-3xl font-semibold">{{ $plan->monthlyPrice() }}</span>
                    <span class="text-sm text-muted-foreground">/ month</span>
                </p>
                <p class="mt-2 min-h-10 text-sm text-muted-foreground">{{ $plan->tagline() }}</p>

                <ul class="mt-4 flex-1 space-y-2 text-sm">
                    @if($plan === \Domain\Billing\Enums\Plan::Free)
                        <li class="flex items-start gap-2"><x-icon.check class="mt-0.5 size-4 shrink-0 text-primary" /> Browsable catalogue</li>
                        <li class="flex items-start gap-2"><x-icon.check class="mt-0.5 size-4 shrink-0 text-primary" /> Supplier management</li>
                    @else
                        @if($plan !== \Domain\Billing\Enums\Plan::Starter)
                            <li class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Everything in {{ ['pro' => 'Starter', 'group' => 'Pro'][$plan->value] ?? '' }}, plus:</li>
                        @endif
                        @foreach($unlockedAt($plan) as $feature)
                            <li class="flex items-start gap-2"><x-icon.check class="mt-0.5 size-4 shrink-0 text-primary" /> {{ $feature->label() }}</li>
                        @endforeach
                    @endif
                </ul>

                <div class="mt-6">
                    @if($isCurrent)
                        <x-button variant="outline" class="w-full" disabled>Current plan</x-button>
                    @elseif($plan === \Domain\Billing\Enums\Plan::Free)
                        <x-button variant="outline" class="w-full" disabled>Free</x-button>
                    @elseif($plan->rank() > $currentPlan->rank())
                        <x-button wire:click="checkout('{{ $plan->value }}')" class="w-full">Upgrade</x-button>
                    @else
                        <x-button wire:click="checkout('{{ $plan->value }}')" variant="outline" class="w-full">Switch</x-button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if($currentPlan->isPaid())
        <div class="text-center">
            <x-button wire:click="billingPortal" variant="ghost" size="sm">
                <x-icon.credit-card class="size-4" />
                Manage billing
            </x-button>
        </div>
    @endif
</div>
