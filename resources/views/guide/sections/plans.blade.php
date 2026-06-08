@php
    use Domain\Billing\Enums\Plan;
    use Domain\Billing\Enums\Feature;

    $plans = [Plan::Free, ...Plan::paid()];
@endphp

<p>CellarOS has four plans. Everyone can browse the catalogue and manage suppliers; paid plans unlock importing, ordering, inventory and more. There's no currency conversion, money is shown in your venue's base currency.</p>

<h2>At a glance</h2>
<ul>
    @foreach($plans as $plan)
        <li><strong>{{ $plan->getLabel() }}</strong> ({{ $plan->monthlyPrice() }}/mo), {{ $plan->tagline() }}</li>
    @endforeach
</ul>

<h2>Feature matrix</h2>
<div class="not-prose overflow-x-auto rounded-lg border border-border">
    <table class="w-full text-sm">
        <thead class="border-b border-border bg-secondary/40">
            <tr>
                <th class="px-3 py-2 text-left font-medium text-muted-foreground">Feature</th>
                @foreach($plans as $plan)
                    <th class="px-3 py-2 text-center font-medium text-muted-foreground">{{ $plan->getLabel() }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-border">
            <tr>
                <td class="px-3 py-2 text-foreground">Catalogue &amp; suppliers</td>
                @foreach($plans as $plan)<td class="px-3 py-2 text-center text-primary">✓</td>@endforeach
            </tr>
            @foreach(Feature::cases() as $feature)
                <tr>
                    <td class="px-3 py-2 text-foreground">{{ $feature->label() }}</td>
                    @foreach($plans as $plan)
                        <td class="px-3 py-2 text-center {{ $plan->can($feature) ? 'text-primary' : 'text-muted-foreground/40' }}">{{ $plan->can($feature) ? '✓' : '–' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<p>Upgrade any time from the <a href="{{ route('pricing') }}" wire:navigate>pricing page</a>, see <a href="{{ url('/guide/billing') }}" wire:navigate>Plans &amp; billing</a>.</p>
