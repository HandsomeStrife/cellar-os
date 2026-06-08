<p>The import wizard turns a supplier's CSV or Excel price list into catalogue wines in four steps.</p>
<p class="meta">Route: <code>/import</code> · Requires the <strong>Starter</strong> plan or above</p>

<h2>Step 1 — Upload</h2>
<ol>
    <li>Choose the <strong>supplier</strong> the list belongs to.</li>
    <li>Upload a <code>.csv</code>, <code>.xls</code> or <code>.xlsx</code> file (up to 10 MB).</li>
</ol>
<p>CellarOS reads the headers and rows, and if it has imported from this supplier before, it pre-loads their saved column mapping.</p>

<h2>Step 2 — Map columns</h2>
<p>Match each CellarOS field (wine name, producer, country, region, grape, colour, vintage, format, case size, unit price, stock) to a column from your file. The wizard auto-guesses from the header names; you only fix what it got wrong. <strong>Wine name is required.</strong></p>

<h2>Step 3 — Preview</h2>
<p>See a sample of how your wines will be imported, after normalisation:</p>
<ul>
    <li><strong>Colours</strong> are recognised across languages (e.g. <em>rouge</em>, <em>bianco</em>, <em>champagne</em> → Sparkling).</li>
    <li><strong>Grapes &amp; regions</strong> are standardised (e.g. <em>Shiraz</em> → Syrah, <em>Burgundy</em> → Bourgogne).</li>
    <li><strong>Prices, vintages and bottle formats</strong> are parsed from messy text (currency symbols, <em>75cl</em>, <em>Magnum</em>, "NV", etc.).</li>
    <li>Each wine is <strong>geocoded</strong> from its region/country so it appears on the <a href="{{ url('/guide/map') }}" wire:navigate>sourcing map</a>.</li>
</ul>

<h2>Step 4 — Import</h2>
<p>Confirm to create the wines, linked to the supplier. Re-importing an updated list <strong>updates existing wines</strong> (matched on supplier + name + vintage + format) rather than duplicating them, and the column mapping is saved back to the supplier for next time.</p>

<div class="callout">
    Importing from a <strong>PDF</strong> price list is planned for a later release.
</div>
