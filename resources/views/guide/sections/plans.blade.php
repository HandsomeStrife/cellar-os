@php
    use Domain\Billing\Enums\Plan;
    use Domain\Billing\Enums\Feature;

    $plans = Plan::paid();
@endphp

<p>CellarOS has two plans. <strong>Pro</strong> carries the whole day-to-day product for a single venue: suppliers, price lists, catalogue, purchase orders and inventory. <strong>Group</strong> is the same across every venue in the group. There's no currency conversion, money is shown in your venue's base currency.</p>

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

@if(config('features.pricing'))
    <p>Upgrade any time from the <a href="{{ route('pricing') }}" wire:navigate>pricing page</a>, see <a href="{{ url('/guide/billing') }}" wire:navigate>Plans &amp; billing</a>.</p>
@else
    <p>To move between plans, get in touch and we'll switch you over.</p>
@endif
