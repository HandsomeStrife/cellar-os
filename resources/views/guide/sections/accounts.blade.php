<p>Everything in CellarOS hangs off your account and your venue. This section covers signing up, what a venue is, currency, and how plans gate features.</p>

<h2>Creating an account</h2>
<p class="meta">Route: <code>/register</code></p>
<p>Registration captures your name, email and password, plus three things that set up your workspace:</p>
<dl>
    <dt>Company / venue</dt>
    <dd>Becomes your first <strong>venue</strong>, the place you hold stock. You can rename it later.</dd>
    <dt>Base currency</dt>
    <dd>GBP, EUR or USD. Prices and totals display in this currency throughout the app. (CellarOS does not convert between currencies, it simply shows and snapshots the right symbol.)</dd>
    <dt>Profession</dt>
    <dd>Optional, owner, buyer, manager, sommelier or consultant.</dd>
</dl>
<p>New accounts start on the <strong>Free</strong> plan. Sign in at <code>/login</code>; reset a forgotten password from the link on that page.</p>

<h2>Venues</h2>
<p>A venue is a location that holds inventory (a restaurant, bar or store). Your first venue is created at signup. Inventory and "deliver-to" addresses are per-venue. Running <strong>more than one venue</strong> requires the Group plan.</p>

<h2>Plans &amp; feature gating</h2>
<p>CellarOS has four tiers, <strong>Free, Starter, Pro, Group</strong>, each unlocking more. When a feature needs a higher plan, you'll see an upgrade prompt with a link to <a href="{{ route('pricing') }}" wire:navigate>pricing</a>. The full breakdown is in the <a href="{{ url('/guide/plans') }}" wire:navigate>Plan &amp; feature matrix</a>.</p>

<h2>Administrators</h2>
<p>Staff administrators are entirely separate from normal accounts, a different login at <code>/admin</code> with its own guard. See <a href="{{ url('/guide/admin') }}" wire:navigate>Admin back-office</a>.</p>
