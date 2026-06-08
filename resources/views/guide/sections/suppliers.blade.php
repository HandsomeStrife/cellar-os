<p>Suppliers are the merchants and importers you buy from. They're shared across your workspace and are referenced by products and purchase orders.</p>
<p class="meta">Route: <code>/suppliers</code></p>

<h2>Adding a supplier</h2>
<ol>
    <li>Open <strong>Suppliers</strong> and click <strong>New supplier</strong>.</li>
    <li>Enter the name (required), plus optional contact name, email, phone and location.</li>
    <li>Choose a status, <strong>Active</strong> or <strong>Inactive</strong>.</li>
    <li>Save. The supplier appears as a card.</li>
</ol>

<h2>Managing suppliers</h2>
<ul>
    <li><strong>Search</strong> the cards by name, contact, email or location.</li>
    <li><strong>Edit</strong> a supplier from its card to update details.</li>
    <li><strong>Toggle status</strong> by clicking the status badge, handy for pausing a supplier without deleting them.</li>
    <li><strong>Delete</strong> suppliers you no longer use.</li>
</ul>

<h2>Why the email matters</h2>
<p>A supplier's email address is used when you <a href="{{ url('/guide/orders') }}" wire:navigate>email a purchase order</a>. If it's blank, CellarOS will tell you rather than send into the void.</p>

<div class="callout">
    CellarOS also remembers each supplier's <strong>price-list column layout</strong> the first time you import from them, so the next import maps itself. See <a href="{{ url('/guide/import') }}" wire:navigate>Importing price lists</a>.
</div>
