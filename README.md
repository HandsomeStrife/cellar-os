# CellarOS

> The operating system for the modern wine trade — inventory, suppliers, purchase orders, supplier price-list imports, and a global sourcing map.

CellarOS is a Laravel + TALL-stack (Tailwind, Alpine, Livewire, Laravel) application built with a strict Domain-Driven Design layout: **all business logic lives in `/domain`**, while `app/` holds only the HTTP/Livewire/framework glue.

It is a re-implementation of the original CellarOS (a React/Express/Drizzle/Postgres app kept as a functional reference at <https://github.com/HandsomeStrife/CellarOS>).

> **Working on this codebase?** `CLAUDE.md` is the canonical, in-depth reference — architecture, patterns, critical rules, component library, schema, and conventions. Read it first. This README is the quick orientation + run guide.

---

## Tech stack

| | |
|---|---|
| Framework | Laravel 13 (PHP `^8.3`; the container runs PHP 8.5) |
| Frontend | TALL — Tailwind v4 (`@tailwindcss/vite`, CSS-variable theme), Alpine (bundled by Livewire), Livewire 4 (class-based), Blade |
| Database | MySQL `cellar_os` (Cerberus shared instance); tests run on SQLite `:memory:` |
| DTOs | `spatie/laravel-data` (Wireable) |
| Billing | Laravel Cashier (Stripe) |
| PDF / Excel | `barryvdh/laravel-dompdf`, `phpoffice/phpspreadsheet` |
| Map | Leaflet + OpenStreetMap (tokenless) |
| Tests | Pest 4 (unit/feature) + Playwright (E2E) |
| Fonts | Hanken Grotesk (UI), Archivo (display), IBM Plex Mono (data) via bunny fonts |

---

## Local development

The site is managed by the **Cerberus** CLI and runs in a Sail container named `cellar-os-app`.

```bash
# Lifecycle (from anywhere)
cerberus start cellar-os          # boot containers + Vite
cerberus restart cellar-os        # restart (re-patches compose, restarts Vite)
cerberus restart-vite cellar-os   # just restart Vite
cerberus logs cellar-os           # tail Vite output

# Artisan / Composer / NPM run INSIDE the container
docker exec cellar-os-app php artisan <cmd>
docker exec cellar-os-app composer <cmd>
docker exec cellar-os-app npm run build

# …or via Sail from the project dir
./vendor/bin/sail artisan <cmd>

# Tests & formatting (host PHP is fine for these)
./vendor/bin/pest                 # unit + feature (SQLite :memory:)
./vendor/bin/pint                 # PSR-12 formatting
npx playwright test               # E2E (run `npx playwright install chromium` once)
```

- **App URL:** <http://cellar-os.cerberus.local>
- **Mail:** captured by shared Mailpit — <http://mailpit.cerberus.local/> (SMTP `host.docker.internal:1025`)
- **DB:** MySQL `cellar_os` on the shared `mysql` container

### First run

```bash
cerberus start cellar-os
docker exec cellar-os-app php artisan migrate:fresh --seed   # schema + demo data
docker exec cellar-os-app npm run build                      # or let Vite serve in dev
```

---

## Demo accounts

`php artisan migrate:fresh --seed` (idempotent) creates a shared catalogue (3 suppliers, 10 geo-located wines), a default admin, and **one demo user per plan tier**, each showing a different point in the journey (with their own venues/inventory/orders). **All passwords are `password`.** The list is also surfaced in-app at `/guide/demo-logins`.

| Login | Where | Plan | Shows |
|-------|-------|------|-------|
| `admin@cellaros.test` | `/admin` | — | Back-office: overview, users, enquiries |
| `free@cellaros.test` | `/login` | Free | Brand-new: empty / getting-started state |
| `starter@cellaros.test` | `/login` | Starter | A draft + sent order, a little received stock |
| `demo@cellaros.test` | `/login` | Pro | Full single venue: stock + orders across the lifecycle |
| `group@cellaros.test` | `/login` | Group | Two venues, each with stock + orders |

---

## Features

All bounded contexts ship with a working UI + tests:

- **Auth** — login / register (captures company→venue, base currency, profession) / logout / password reset (session, DDD-aligned).
- **Dashboard** — KPI tiles, inventory breakdowns by colour/country/region, recent orders, low-stock alerts, getting-started guide.
- **Guide** (`/guide`, public) — documentation-style site with its own doc layout + sticky sidenav; one URL per section, written for a layperson.
- **Suppliers** — card-grid CRUD, status toggle, saved import column mappings.
- **Catalogue** — sortable/filterable product table, inline price edit, session basket that creates one draft PO per supplier.
- **Import** — CSV/Excel → column mapping → preview → import wizard (colour/grape/region normalisation, price/vintage/format parsing, geocoding). _Starter+._
- **Orders** — list + create (basket or manual), status lifecycle, PDF (dompdf), email to supplier (Mailpit), and Receive → inventory. _createPOs / email gated Starter+._
- **Inventory** — per-venue stock, quantity stepper, archive/restore, private file attachments. _Gated: Starter+ page, Pro+ manual add/archive/attachments, Group 2nd+ venue._
- **Billing** — `/pricing` plan cards, Cashier checkout, webhook plan-sync (fail-closed without `STRIPE_WEBHOOK_SECRET`).
- **Map** — `/map` Leaflet global sourcing view.
- **Enquiries** — public contact form on the landing → stored in `enquiries`; reviewed at `/admin/enquiries`.
- **Admin** — fully separate `admin` guard at `/admin`: login (throttled), dashboard, user management, enquiry review.

Plan gating is enforced both in-component (`Plan::can(Feature)`) and via the `feature:<key>` route middleware; the UI shows `x-upgrade-gate`.

---

## Architecture at a glance

Domain-Driven Design — see `CLAUDE.md` for the full rules and code patterns.

| Layer | Location | Purpose |
|-------|----------|---------|
| Action | `domain/<Ctx>/Actions/` | A single write operation: one `execute()`, accepts & returns DTOs |
| Repository | `domain/<Ctx>/Repositories/` | Read-only queries, **always return DTOs** (never Eloquent models) |
| Data (DTO) | `domain/<Ctx>/Data/` | Immutable, Wireable; extend `AbstractData` |
| Model | `domain/<Ctx>/Models/` | Eloquent mapping only: `$fillable`, `casts()`, `getData()`; no business logic |
| Service | `domain/<Ctx>/Services/` | External integrations only (Stripe, PDF, mail, parsing) |
| Enum | `domain/<Ctx>/Enums/` | Backed enums with helper methods |
| Livewire | `app/Livewire/` | UI components; use repositories/actions, never models directly |

**Bounded contexts:** `Shared`, `User`, `Admin` (separate guard/table), `Venue`, `Supplier`, `Catalogue`, `Import`, `Order`, `Inventory`, `Billing`, `Enquiry`.

**Non-negotiables** (full list in `CLAUDE.md`): `declare(strict_types=1)` everywhere; no `app/Models` and no `app/Domain`; no cross-context Eloquent relations (reach other contexts via repositories/actions); bigint PKs + public `uuid` column (`HasUuid`); form inputs are `x-input.*` components, never raw `<input>`; icons are Blade components, never inline SVG; retrieve the user via `(new UserRepository())->getLoggedInUser()`, never `Auth::user()` in domain code.

---

## Testing

```bash
./vendor/bin/pest          # Pest unit + feature on SQLite :memory: (RefreshDatabase)
npx playwright test        # E2E against the dev site (logs in the demo user)
```

- Factories exist for every model in `database/factories`. Cross-context FKs are left null in factories — set them explicitly in tests.
- Playwright config in `playwright.config.ts`; `auth.setup.ts` persists a logged-in session, `global-setup.ts` seeds the dev DB (`E2E_SKIP_SEED=1` to skip).

---

## Notable deliberate choices

Recorded so future sessions don't "fix" them (see `CLAUDE.md` for the full rationale):

- **MySQL**, not the upstream's Postgres; `pgEnum` columns become plain `string` columns cast to PHP backed enums.
- **bigint PKs + a public `uuid`** rather than UUID primary keys (keeps Cashier migrations unmodified).
- **Auth is plain Livewire/session**, DDD-aligned (the stock starter kit's `app/Models/User` would violate the "models live in `/domain`" rule).
- **Admins are a fully separate domain**, table, and guard.
- The **marketing landing is a standalone Blade page with no Livewire/Alpine** (small vanilla JS for its interactivity) and is always light-themed.
