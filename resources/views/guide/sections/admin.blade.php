<p>The admin back-office is a separate area for CellarOS staff — a completely separate login and account type from normal users.</p>
<p class="meta">Route: <code>/admin</code></p>

<h2>Separate by design</h2>
<p>Administrators authenticate against their own <code>admin</code> guard with their own credentials at <code>/admin/login</code>. An admin session is independent of a normal user session — being signed in as an admin does not grant access to the user app, and vice-versa. The login is rate-limited.</p>

<h2>What admins can do</h2>
<dl>
    <dt>Dashboard</dt>
    <dd>Platform-wide totals — users, suppliers, wines and orders.</dd>
    <dt>Users</dt>
    <dd>Search accounts, change a user's plan, or remove a user. Deleting a user cancels any active subscription first so billing doesn't continue.</dd>
</dl>

<div class="callout">
    Every destructive admin action re-checks the admin guard, so it can't be triggered from a normal user session.
</div>
