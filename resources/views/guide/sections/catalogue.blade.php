<p>The catalogue is every wine you trade, in one sortable, filterable table. It's also where you build orders.</p>
<p class="meta">Route: <code>/catalogue</code></p>

<h2>Finding wines</h2>
<ul>
    <li><strong>Ask in plain English</strong> in the box at the top: "something fizzy and pink for a wedding, under £40". CellarOS turns your sentence into the ordinary filters below and tells you what it understood, so you can see and change any part of it. Each result says why it's there.</li>
    <li><strong>Search</strong> by wine name or producer.</li>
    <li><strong>Filter</strong> by type, style, country, region, producer, grape, price and vintage. Filters are kept in the URL, so a filtered view is shareable and bookmarkable.</li>
    <li><strong>Sort</strong> any column (name, producer, supplier, country, region, vintage, price), click the header again to flip the direction.</li>
    <li><strong>Columns</strong> lets you choose which details are shown in the table — your choice is remembered. The supplier column hides itself when you've filtered to a single supplier.</li>
    <li><strong>Click a wine's name</strong> to open a panel with everything CellarOS knows about it.</li>
</ul>

<h2>Type and style</h2>
<p>Every wine has a <strong>type</strong>: red, white, rosé, orange, sparkling, dessert or fortified. Some types also have a <strong>style</strong> within them — a sparkling wine may be a sparkling white, rosé or red, or a pét-nat; a fortified wine may be port, sherry, madeira or vermouth.</p>
<p>Filtering by a type always includes every style beneath it, so asking for sparkling shows the rosés too. Pick a style only when you want to narrow further.</p>

<h2>Wines with no price</h2>
<p>Some suppliers deliberately don't print a price — tiny allocations, or wines they'd rather quote for. Those show as <strong>POA</strong> (price on application) or <strong>TBC</strong> instead of a figure, with the supplier's own wording when they gave one. Hover the badge to read it.</p>
<p>A POA wine is a real listing and stays in your catalogue; it simply has no price yet. You can still put one on an order — the line prints as POA and is left out of the total, so you and the supplier agree the figure when they confirm.</p>

<h2>Filled-in wine details</h2>
<p>Your supplier's own information always comes first. When their list leaves a gap — a missing grape variety, type or origin — CellarOS fills it in from elsewhere and clearly says so:</p>
<ul>
    <li>A <strong>book icon</strong> means the detail comes from the <strong>Liv-ex LWIN wine database</strong>, the wine trade's shared reference of more than 200,000 wines.</li>
    <li>A <strong>sparkle icon</strong> means another supplier on CellarOS lists the same wine and provided the detail (we never say which supplier).</li>
</ul>
<p>Hover over the dotted underline to see where a value came from. Nothing your supplier provides is ever changed or overwritten — these fills only appear where their list said nothing, and if different sources disagree about a detail, CellarOS leaves it blank rather than guess.</p>

<h2>Editing a price</h2>
<p>Click a price to edit it inline; press Enter to save. CellarOS recalculates the price-per-litre from the bottle format automatically.</p>

<h2>The order basket</h2>
<ol>
    <li>Click <strong>+</strong> on a wine. A panel opens where you choose <strong>how many</strong> — bottles, or cases for a wine sold by the case — with the line total as you go.</li>
    <li>If another of your suppliers lists the <em>same</em> wine, the panel shows them too, cheapest first, with the difference per bottle spelled out. <strong>Use this one</strong> switches to that supplier and keeps your quantity.</li>
    <li>Open the <strong>Basket</strong> to adjust quantities, see line and grand totals, or clear it.</li>
    <li>Click <strong>Create purchase orders</strong>. CellarOS groups the basket <strong>by supplier</strong> and creates one draft PO per supplier, then takes you to <a href="{{ url('/guide/orders') }}" wire:navigate>Orders</a>.</li>
</ol>
<p>Only suppliers you're connected to are ever compared, so you never see pricing from a merchant you don't work with.</p>

<h2>Deleting a wine</h2>
<p>Use the trash icon on a row to remove a wine from the catalogue. The basket persists as you browse, so you won't lose your selection.</p>
