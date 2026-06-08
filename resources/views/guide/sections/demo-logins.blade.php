@php
    $accounts = [
        ['demo@cellaros.test', 'Pro', 'A fully operational single venue: received stock plus purchase orders across the whole lifecycle (draft, sent and received).'],
        ['free@cellaros.test', 'Free', 'A brand-new account: the empty dashboard and getting-started checklist, before any stock or orders exist.'],
        ['starter@cellaros.test', 'Starter', 'Getting going: a draft and a sent order, with a little received stock building up.'],
        ['group@cellaros.test', 'Group', 'A multi-venue operation: two venues, each with their own inventory and orders.'],
    ];
@endphp

<p>The demo environment ships with ready-made accounts so you can explore CellarOS from a few different starting points. Every account uses the password <code>password</code>.</p>

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
<p>The back-office is a separate login from the user app. Use <code>admin@cellaros.test</code> with the password <code>password</code> to reach the platform overview, user management and <a href="{{ url('/guide/admin') }}" wire:navigate>enquiry review</a>.</p>

<p>New to the app? Start with the <a href="{{ url('/guide/welcome') }}" wire:navigate>five-minute quick start</a>, or read about <a href="{{ url('/guide/accounts') }}" wire:navigate>accounts, venues &amp; plans</a>.</p>
