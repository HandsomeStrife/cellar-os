# Changelog

All notable changes to CellarOS are recorded here. Format loosely follows
[Keep a Changelog](https://keepachangelog.com/); dates are ISO‑8601.

## [Unreleased]

### Catalogue UX overhaul — 2026-06-29

A focused pass over the catalogue (browse/filter/price) driven by the trade
bug list, plus the first stage of case‑vs‑unit pricing. Shipped to `main`;
328+ Pest tests green throughout.

#### Added — full‑featured catalogue filters (Phase 1) · `2ef024e`
- A collapsible **"Filters" panel** (Alpine) covering every filterable column:
  **region, sub‑region, producer, grape, price range (min/max) and vintage
  range (min/max)**, on top of the existing search / colour / supplier.
- **Cascading dropdowns**: changing country clears region + sub‑region;
  changing region clears sub‑region. An active‑filter **count badge** on the
  toggle and a **Clear filters** action.
- `ProductRepository::search()` extended with the new criteria; new
  `regions()` / `subRegions()` distinct‑value helpers that cascade by the
  broader selection; `SORTABLE` now includes region/sub‑region.
- Richer table cells: **sub‑region** added to Origin, **price‑per‑litre** shown
  under price.
- Migration `2026_06_29_120000_add_catalogue_filter_indexes` indexes
  region/sub_region/producer/vintage/unit_price to keep the panel fast across
  the ~12k‑row catalogue.
- Acceptance case "a white wine from Burgundy under £20" works and is
  test‑covered (`ProductFilterTest`, `BrowseCatalogueTest`).
- _Known ceiling:_ region/producer are only ~89% / ~74% populated, so those
  filters structurally miss wines with empty columns (same authoritative‑only
  limit as `wine:backfill-attributes`).

#### Added — case vs unit pricing, model + display (Phase 2a) · `70577f2`
- New `Domain\Catalogue\Enums\SellingUnit` (`bottle` | `case`).
- Migration `2026_06_29_130000_add_selling_unit_to_products` adds
  `products.sold_by` (default `bottle`) and `products.pack_price` (the
  supplier's exact quoted case price; null = derive from `unit_price` ×
  `case_size`). **`unit_price` remains the canonical per‑bottle price**, so
  cross‑supplier sort / filter / comparison are unaffected.
- `ProductData` carries the new fields plus pure display helpers —
  `soldByCase()`, `displayPrice()` (native selling unit; exact pack price
  preferred over the derived one), `perBottleEquivalent()`.
- Catalogue price cell now shows e.g. **`£1,275 /case`** with
  **`≈ £212.50 / btl`** underneath; bottle‑sold wines are unchanged.
- Threaded through the whole write path: `UpsertProductAction`, golden
  export/import (`ExportGoldenSnapshot` + `ImportCatalogueWinesAction`, which
  also backs `/api/ingest/wines`), `ProductFactory` (+ a `soldByCase()`
  state). `NormaliseService` defaults to `bottle` for now — per‑row detection
  is Phase 2b.
- `sold_by` / `pack_price` are deliberately **not** wine facts (kept out of
  `ContributeWineFactsAction`; the facts store is price‑free by policy).

#### Changed — catalogue polish & perceived speed (Phase 0) · `2ef024e`
- **Removed the "Stock" column** from the catalogue table — it surfaced raw
  supplier‑provided `products.stock`, easily confused with venue inventory.
  The column stays in the DB; it is dropped from the table and from
  `ProductRepository::SORTABLE`.
- **Cursor & hover affordances**: a global rule gives every actionable element
  a `pointer` cursor (and disabled controls `not-allowed`), so buttons, sort
  headers and icon actions read as clickable.
- **Livewire perceived‑speed fixes**: a loading veil dims the table on any
  filter/sort/paginate request, and `wire:loading` guards disable
  add‑to‑basket (icon swaps to a spinner), delete and save‑price to stop
  double‑clicks. New `loader-circle` and `sliders-horizontal` Lucide icons.

#### Notes
- All work committed directly to `main` and pushed to
  `github.com:HandsomeStrife/cellar-os`.
- Remaining case‑vs‑unit work is tracked on the board: **2b** parser detection
  (per‑row per‑case vs per‑bottle), **2c** basket/orders by the case
  (+ receive→inventory conversion), **2d** audit & fix already‑live mis‑priced
  wines (the Alabaster / Stodden / `1275/case` spot checks). A separate backend
  redesign track (light‑mode default → dashboard → full pages + sidenav) is
  also queued.
