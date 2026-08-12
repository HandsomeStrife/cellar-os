@if($downgraded())
    <div class="border-b border-border bg-destructive/10 px-4 py-2.5 text-sm text-foreground sm:px-6">
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
            <x-icon.circle-alert class="size-4 shrink-0 text-destructive" />
            <span class="font-medium">Your {{ $company->plan->getLabel() }} trial has ended.</span>
            <span class="text-muted-foreground">You're on {{ $company->effectivePlan()->getLabel() }} now. Talk to us to carry on where you left off.</span>
        </div>
    </div>
@else
    <div class="border-b border-border bg-primary/10 px-4 py-2.5 text-sm text-foreground sm:px-6">
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
            <x-icon.sparkles class="size-4 shrink-0 text-primary" />
            <span class="font-medium">{{ $daysLeft() }} {{ $daysLeft() === 1 ? 'day' : 'days' }} left of your {{ $company->plan->getLabel() }} trial.</span>
            <span class="text-muted-foreground">Everything stays as it is until {{ $company->trial_ends_at->format('j F') }}.</span>
        </div>
    </div>
@endif
