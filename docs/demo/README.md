# Demoing CellarOS

The full walkthrough — with screenshots and a cheatsheet — is
**[`demo-guide.html`](demo-guide.html)** in this folder. Open it in a browser.

This file is the short version, for when you just need the commands.

## Reset the demo

```bash
php artisan demo:reset          # asks first
php artisan demo:reset --force  # doesn't
```

Run it **before** a demo (so you don't inherit the last person's basket) and
**after** one (so the next person doesn't inherit yours).

It removes only the three demo companies and their five private merchants, then
rebuilds them. Real customers, real supplier catalogues and every other tenant's
data are untouched — there's a test for exactly that.

## Logins

Password is `password` for all of them.

| Sign in at  | Account                      | Shows                                             |
|-------------|------------------------------|---------------------------------------------------|
| `/login`    | `demo@cellaros.test`         | Pro — one venue, three merchants, the whole journey |
| `/login`    | `group@cellaros.test`        | Group — two venues, per-venue merchants, a team     |
| `/login`    | `group.member@cellaros.test` | A member scoped to the Riverside venue only         |
| `/login`    | `trade@cellaros.test`        | Every real supplier we hold a list for — the parsed catalogue at full size |
| `/admin`    | `admin@cellaros.test`        | Back office: suppliers, parsing, AI costs           |
| `/supplier` | `supplier@cellaros.test`     | The merchant's own portal                           |

## What the demo data is

Fictional merchants (Northbank, Ashgrove, Corvina, Halliwell, Saltmarsh)
carrying **real** wine data — names, producers, regions and grapes sampled from
public trade catalogues this environment has parsed, spread across countries so
the list reads like a merchant's rather than a test fixture. A bare install with
nothing imported falls back to a curated list and still demos.

The merchants are **private to the demo companies**. That's what makes
`demo:reset` safe to run anywhere, keeps the demo invisible to real tenants and
out of golden snapshots, and lets the demo account edit prices, delete wines and
approve parsed lists — all of which are restricted to a company's own private
suppliers.

## The trade-reference account

`trade@cellaros.test` is the odd one out and is **not** part of the scripted
walkthrough. It owns nothing and trades with nobody invented: it is connected to
every **public** supplier that has wines, so it shows the real parsed catalogue
at full size. Use it to answer "how many wines have you actually got?", to check
a parse through the app rather than the database, or to search the real trade
data with the catalogue's own filters.

Because it only *connects* to those suppliers rather than owning them, it can't
edit a price, delete a wine or approve a parsed list — all three are
private-supplier-only. That makes it safe to hand to someone outside the team.

## What's live, and what isn't

Two finished areas are switched **off** by feature flag (`config/features.php`),
so don't demo them and don't be surprised when the nav is missing them:

| Area | Flag | State |
|------|------|-------|
| Sourcing map | `FEATURE_MAP` | Off. Routes 404, hidden from nav and guide. |
| Pricing & self-serve checkout | `FEATURE_PRICING` | Off. `/pricing` 404s; plan changes are an admin job. |

Everything else in the sidebar is live. Plans are **Pro** and **Group** only;
Group's single addition is trading across more than one venue.

## What's deliberately arranged

Change any of this in `database/seeders/DemoSeeder.php` and re-run `demo:reset`.

- A FIXED comparison pair — Côtes du Rhône Villages "Les Galets", Domaine de
  la Fontclaire — listed by Northbank at £14.00 and Ashgrove at £11.60. The
  sampled wines produce overlaps too, but their names depend on what this
  environment parsed, so the guide points at this pair instead.
- The same four sampled wines from two merchants at different prices → more
  cross-merchant price comparison.
- One wine held at **POA** with the merchant's own wording.
- Sparkling in four styles plus port and sherry → Type and Style.
- Case-quoted and bottle-quoted lines side by side.
- Three orders across the lifecycle; the **received** one was placed at ~8%
  lower prices, so repeating it shows the change report.
- Two stock lines low enough to trip the dashboard's low-stock alerts.
- A price list awaiting review, with one flagged row that bulk-approve skips.
- Two unmapped type words (`Skin Contact`, `Vin de Voile`) waiting on the
  review screen.
- Portal documents awaiting analysis, analysed, and failed; plus a merchant
  whose invite hasn't been accepted.

## Feature coverage, and where to show each one

Every live area, and the account that demonstrates it best.

| Feature | Account | Where |
|---------|---------|-------|
| KPIs, low-stock alerts, recent orders | `demo@` | Dashboard |
| Catalogue table, Producer/Supplier columns, column picker | `demo@` | Catalogue |
| Type + Style (sub-type) cascading filter | `demo@` | Catalogue filters |
| AI Search in plain English, with reasons | `demo@` | Catalogue search box |
| Add with a quantity, in the wine's selling unit | `demo@` | Catalogue, the **+** button |
| Cross-supplier price comparison | `demo@` | The add panel (see the guide for which wine) |
| POA / TBC wines carrying the merchant's wording | `demo@` | Catalogue, and on a sent PO |
| Basket → purchase orders, split by merchant | `demo@` | Basket |
| Order lifecycle, PDF, email to supplier | `demo@` | Orders |
| Repeat an order, re-priced with a change report | `demo@` | Orders, the received one |
| Receive an order into stock | `demo@` | Orders → Receive |
| Inventory with full wine detail + column picker | `demo@` | Inventory |
| Attachments on a stock line | `demo@` | Inventory |
| Price-list review: approve / edit / reject | `demo@` | Suppliers → documents → Review |
| Teaching a merchant's type words | `demo@` | Review screen, or `/admin/suppliers` |
| Connecting to a supplier, private vs listed | `demo@` | Suppliers, Discover tab |
| Multiple venues, per-venue merchants and stock | `group@` | Venue selector |
| Team, roles, venue-scoped members | `group@` | Team |
| A member who can only see one venue | `group.member@` | Anywhere |
| The real catalogue at full size | `trade@` | Catalogue |
| Supplier CRM, tiers, parse profiles, notes | `admin@` | `/admin/suppliers` |
| Import history and cost per supplier | `admin@` | `/admin/suppliers/{uuid}` |
| AI spend ledger | `admin@` | `/admin/costs` |
| Impersonating a user or a supplier | `admin@` | Eye icon in admin lists |
| Enquiries from the landing page | `admin@` | `/admin/enquiries` |
| The supplier's own portal + upload | `supplier@` | `/supplier` |

Not demoable from the seeded data: the sourcing map and pricing/checkout (both
flagged off), and LWIN enrichment markers, which only appear where a real
catalogue has gaps — use `trade@` for those.

## Rebuilding the guide's screenshots

The guide's screenshots are captured from a freshly reset demo. If the UI
changes enough to date them, re-shoot against `demo:reset` state and rebuild
`demo-guide.html` — the page is self-contained (fonts and images are inlined),
so there is nothing else to deploy.

## The run-sheet

**[`run-sheet.html`](run-sheet.html)** is the page to give someone who has to
demo the platform but didn't build it: the run in order, the exact wines to
search so the price comparison has something to show, the awkward questions with
answers, and the two data problems in the real catalogue to steer around. It is
self-contained, so it opens from disk with nothing else to deploy.

It is also served by the app at **`/demo`** — same file, read at request time by
`DemoRunSheetController`, so the web copy can never drift from the one on disk.
That page is public (we're pre-launch) but carries a `noindex`, and it names a
supplier whose prices we currently have wrong, so give out the link rather than
linking to it from anywhere.

Where `demo-guide.html` shows the staged demo with screenshots, the run-sheet
covers both accounts and includes the real catalogue.
