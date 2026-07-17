<p>The catalogue is every wine you trade, in one sortable, filterable table. It's also where you build orders.</p>
<p class="meta">Route: <code>/catalogue</code></p>

<h2>Finding wines</h2>
<ul>
    <li><strong>Search</strong> by wine name or producer.</li>
    <li><strong>Filter</strong> by country and colour. Filters are kept in the URL, so a filtered view is shareable and bookmarkable.</li>
    <li><strong>Sort</strong> any column (name, country, region, vintage, price), click the header again to flip the direction.</li>
    <li><strong>Columns</strong> lets you choose which details are shown in the table — your choice is remembered.</li>
    <li><strong>Click a wine's name</strong> to open a panel with everything CellarOS knows about it.</li>
</ul>

<h2>Filled-in wine details</h2>
<p>Your supplier's own information always comes first. When their list leaves a gap — a missing grape variety, colour or origin — CellarOS fills it in from elsewhere and clearly says so:</p>
<ul>
    <li>A <strong>book icon</strong> means the detail comes from the <strong>Liv-ex LWIN wine database</strong>, the wine trade's shared reference of more than 200,000 wines.</li>
    <li>A <strong>sparkle icon</strong> means another supplier on CellarOS lists the same wine and provided the detail (we never say which supplier).</li>
</ul>
<p>Hover over the dotted underline to see where a value came from. Nothing your supplier provides is ever changed or overwritten — these fills only appear where their list said nothing, and if different sources disagree about a detail, CellarOS leaves it blank rather than guess.</p>

<h2>Editing a price</h2>
<p>Click a price to edit it inline; press Enter to save. CellarOS recalculates the price-per-litre from the bottle format automatically.</p>

<h2>The order basket</h2>
<ol>
    <li>Click <strong>+</strong> on a wine to add it to the basket (click again to increase the quantity).</li>
    <li>Open the <strong>Basket</strong> to adjust quantities, see line and grand totals, or clear it.</li>
    <li>Click <strong>Create purchase orders</strong>. CellarOS groups the basket <strong>by supplier</strong> and creates one draft PO per supplier, then takes you to <a href="{{ url('/guide/orders') }}" wire:navigate>Orders</a>.</li>
</ol>
<p class="meta">Creating orders requires the <strong>Starter</strong> plan or above.</p>

<h2>Deleting a wine</h2>
<p>Use the trash icon on a row to remove a wine from the catalogue. The basket persists as you browse, so you won't lose your selection.</p>
