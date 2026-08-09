<p>Purchase orders move stock from a supplier into your venue. CellarOS takes a PO from draft through to received.</p>
<p class="meta">Route: <code>/orders</code></p>

<h2>Creating an order</h2>
<p>Two ways:</p>
<ul>
    <li><strong>From the catalogue basket</strong>, the quickest path; see <a href="{{ url('/guide/catalogue') }}" wire:navigate>Catalogue</a>. The basket is split into one draft PO per supplier.</li>
    <li><strong>Manually</strong>, click <strong>New order</strong>, choose a supplier and (optionally) a deliver-to venue, search the catalogue to add line items with quantities, add notes, and save.</li>
</ul>

<h2>The lifecycle</h2>
<dl>
    <dt>Draft</dt>
    <dd>A new order. Edit, add a venue, or delete it.</dd>
    <dt>Sent</dt>
    <dd>Set automatically when you email the PO to the supplier (or via the status selector).</dd>
    <dt>Received</dt>
    <dd>Confirms the stock arrived, and pushes it into inventory (see below).</dd>
</dl>
<p>Change the status of any order from the inline status selector in the list.</p>

<h2>Repeating an order</h2>
<p>Ordering the same again is a job you do every week, so any past order has a <strong>Repeat this order</strong> action. It raises a new draft with the same wines and quantities.</p>
<p>The prices are read fresh from your catalogue rather than copied from the old order — a purchase order has to say what the wine costs today. Anything that moved is listed on the new draft before you send it: a changed price (with both figures), a wine that's become POA, one that's no longer listed by that supplier, or one that's gone from your catalogue. Nothing is dropped silently; you decide what to remove.</p>

<h2>PDF &amp; email</h2>
<ul>
    <li><strong>Download PDF</strong>, a branded purchase order, any time.</li>
    <li><strong>Email to supplier</strong>, sends the PDF to the supplier's email and marks the order Sent. Requires the supplier to have an email address.</li>
</ul>

<h2>Receiving into inventory</h2>
<p>When stock for a <strong>Sent</strong> order arrives, click <strong>Receive</strong>. CellarOS adds each line to the order's venue inventory (topping up any existing line) and marks the order <strong>Received</strong>. An order can only be received once. The order must have a venue assigned.</p>
