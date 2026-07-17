@php
    $accounts = [
        ['demo@cellaros.test', 'Pro', 'A fully operational single venue: three connected suppliers, a working cellar (including low-stock alerts on the dashboard), and orders at every point of the lifecycle — draft, sent and received.'],
        ['free@cellaros.test', 'Free', 'A brand-new account: the empty dashboard and getting-started checklist, before any suppliers are connected.'],
        ['starter@cellaros.test', 'Starter', 'Getting going: a first connected supplier, a draft and a sent order, and a little received stock.'],
        ['group@cellaros.test', 'Group', 'A multi-venue operation: two venues with their own suppliers, stock and orders — plus group.member@cellaros.test, a team member scoped to just the Riverside venue.'],
    ];
@endphp

<p>CellarOS ships with ready-made demo accounts so you can explore from a few different starting points. Every account uses the password <code>password</code>, and they browse the platform's <em>real</em> supplier catalogues — thousands of wines from real trade suppliers.</p>

<div class="callout">
    These are sample accounts for trying out CellarOS. They share demo data, so please don't store anything you want to keep in them.
</div>

<h2>User accounts</h2>
<p class="meta">Sign in at <code>/login</code></p>
<div class="not-prose overflow-x-auto rounded-lg border border-border">
    <table class="w-full text-sm">
        <thead class="border-b border-border bg-secondary/40">
            <tr>
                <th class="px-3 py-2 text-left font-medium text-muted-foreground">Email</th>
                <th class="px-3 py-2 text-left font-medium text-muted-foreground">Plan</th>
                <th class="px-3 py-2 text-left font-medium text-muted-foreground">What you'll see</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-border">
            @foreach($accounts as [$email, $plan, $description])
                <tr>
                    <td class="whitespace-nowrap px-3 py-2.5 font-mono text-[13px] text-foreground">{{ $email }}</td>
                    <td class="px-3 py-2.5"><x-badge color="wine">{{ $plan }}</x-badge></td>
                    <td class="px-3 py-2.5 text-muted-foreground">{{ $description }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<p>Password for all accounts: <code>password</code>. <a href="{{ route('login') }}" wire:navigate>Go to sign in</a>.</p>

<h2>Administrator</h2>
<p class="meta">Sign in at <code>/admin</code></p>
<p>The back-office is a separate login from the user app. Use <code>admin@cellaros.test</code> with the password <code>password</code> to reach the platform overview, supplier management (profiles, notes, tiers, document parsing), company and user management, and <a href="{{ url('/guide/admin') }}" wire:navigate>enquiry review</a>.</p>
<p>Administrators can also <strong>impersonate</strong> any user or supplier-portal account — look for the eye icon next to a person in <code>/admin/companies</code>, <code>/admin/users</code> or <code>/admin/suppliers</code>. You'll see the app exactly as they do, with a banner across the top and a one-click "Return to admin". This is the easiest way to preview a supplier's portal before they've even accepted their invite.</p>

<h2>Supplier portal</h2>
<p class="meta">Sign in at <code>/supplier</code></p>
<p>Suppliers have their own portal, separate from both the user app and the admin back-office, where they upload portfolios and price sheets for analysis. Portal accounts are created by an administrator under <code>/admin/suppliers</code>, which emails the supplier an invite link to set their own password — so the portal only has logins for suppliers who have actually been invited.</p>
<p>There are no pre-made portal demo accounts on the live site. To see the portal, either invite a supplier user from <code>/admin/suppliers</code>, or simply impersonate one as an administrator (see above). Development builds can additionally seed a set of fictional portal accounts for testing.</p>

<p>New to the app? Start with the <a href="{{ url('/guide/welcome') }}" wire:navigate>five-minute quick start</a>, or read about <a href="{{ url('/guide/accounts') }}" wire:navigate>accounts, venues &amp; plans</a>.</p>
