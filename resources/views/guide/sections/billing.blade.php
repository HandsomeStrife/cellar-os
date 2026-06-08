<p>CellarOS is subscription-based. The pricing page is where you compare plans and upgrade.</p>
<p class="meta">Route: <code>/pricing</code></p>

<h2>Plans</h2>
<p>Four tiers, each unlocking more — <strong>Free</strong>, <strong>Starter</strong>, <strong>Pro</strong> and <strong>Group</strong>. The pricing page highlights your current plan and lists what each one includes. The full breakdown is in the <a href="{{ url('/guide/plans') }}" wire:navigate>Plan &amp; feature matrix</a>.</p>

<h2>Upgrading</h2>
<ol>
    <li>Open <strong>Pricing</strong> and choose a plan.</li>
    <li>You're taken to secure checkout (Stripe via Laravel Cashier).</li>
    <li>Already subscribed? Choosing a different plan <strong>switches</strong> your subscription rather than creating a second one.</li>
</ol>

<h2>Managing your subscription</h2>
<p>Once subscribed, a <strong>Manage billing</strong> link opens the billing portal to update card details, view invoices or cancel.</p>

<div class="callout">
    Your plan is kept in sync with Stripe automatically via webhooks. If card payments aren't configured in an environment, the pricing page tells you instead of failing.
</div>
