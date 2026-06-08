<p>The sourcing map plots your wines on a world map, so you can see where your range comes from at a glance.</p>
<p class="meta">Route: <code>/map</code></p>

<h2>How wines get on the map</h2>
<p>Each wine is placed using its coordinates. When you <a href="{{ url('/guide/import') }}" wire:navigate>import a price list</a>, CellarOS geocodes wines from their region and country (with a small spread so co-located wines don't stack). Wines without a country/region won't appear.</p>

<h2>Using the map</h2>
<ul>
    <li>Each marker is a wine, coloured by its wine colour.</li>
    <li><strong>Click a marker</strong> to see the wine, producer and country.</li>
    <li>The <strong>by-country</strong> panel breaks down how many geo-located wines you have per country.</li>
</ul>

<div class="callout">
    The map uses OpenStreetMap tiles, no API key or token required.
</div>
