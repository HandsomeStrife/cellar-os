# CellarOS

> The operating system for the modern wine trade — inventory, suppliers, purchase orders, supplier price-list imports, and a global sourcing map.

This is a Laravel + TALL-stack (Tailwind, Alpine, Livewire, Laravel) re-implementation of the original CellarOS (a React/Express/Drizzle/Postgres app, kept as a functional reference at `https://github.com/HandsomeStrife/CellarOS`). It follows a strict Domain-Driven Design layout — all business logic lives in `/domain`.

---

## Version notes / deliberate choices

These differ from the generic `new-laravel-site` scaffold baseline; reasons recorded so future sessions don't "fix" them:

- **Laravel 13.14** (skill baseline says "12+"). Latest stable. `pestphp/pest-plugin-laravel` only resolves on Laravel 13 with Composer's `-W` flag — keep that in mind when adding test deps.
- **PHP 8.5** in the Sail container; codebase requires `^8.3`.
- **Tailwind v4** via `@tailwindcss/vite` (ships with Laravel 13). Theme defined as CSS variables in `resources/css/app.css` (no `tailwind.config.js`).

### Design system (redesigned)

- **Palette:** warm "paper" base with a deep **claret** accent, plus a "cellar" dark mode. All colours are HSL token triples in `app.css` consumed via `hsl(var(--token))`.
- **Type:** `--font-sans` Hanken Grotesk (UI/body), `--font-display`/`--font-serif` Archivo (headings — `font-serif` is aliased to the display face), `--font-mono` IBM Plex Mono (prices/data/labels). Loaded via the laravel-vite-plugin bunny fonts feature in `vite.config.js`.
- **Helpers:** `.select-field` styles native `<select>` (claret chevron, no forms plugin); `accent-color` themes native checkboxes; global `prefers-reduced-motion` guard; `.guide-prose` for docs.
- **Components:** `x-button` (variants incl. `inverse` for image overlays; `focus-visible`), `x-card`, `x-badge`, `x-alert`, `x-stat` (with `tone` + `active`), `x-modal` (`@entangle`), `x-th-sort`, `x-upgrade-gate`, `x-empty-state`, `x-input.{text,email,password,textarea,select,checkbox,search}`, `x-app-logo`. Money via `Domain\Shared\Support\Currency`.
- **Brand mark:** `x-icon.logo` (the supplied CellarOS glyph, `fill="currentColor"` so it adapts to context) used by `x-app-logo` (which has a `markClass` prop, default `text-primary`). Favicon at `public/cellar-os-logo.svg`, linked from every layout `<head>`.
- **Marketing** (`resources/views/landing.blade.php`): **always light** (`<script>` removes the dark class), mobile-first. Full-screen hero **video** behind a header that is transparent (white text) until you scroll, then solid — plus a "Scroll" indicator. Alternating feature sections with **product UI mocks** (a mini catalogue table + a purchase-order doc) and photos; a two-column "connected areas" overview; a two-column included checklist; a contact/enquiry section; image CTA bands; footer with UK company info. The landing has **no Livewire/Alpine** — its header scroll state, mobile menu and reduced-motion video are a small vanilla `<script>` at the foot of the file. NO icon-card grids / bento / editorial layouts / em-dashes. The **pricing** comparison table still exists but is hidden behind an `@if(false)` guard (remove it to restore).
- **Assets:** hero video at `public/media/hero.{webm,mp4}` + `hero-poster.jpg` (optimised from `cellar-os-hero-v2.mp4` via ffmpeg, muted/looping, pauses under reduced motion); curated Pexels imagery in `public/images/` (`CREDITS.txt`, desktop + `-sm` mobile variants).
- **a11y:** focus-visible rings, aria-labelled icon buttons, scoped/`sr-only` pricing table, `<main>` landmarks, ≥40px tap targets, reduced-motion. Keep these when adding UI.
- **MySQL** (Cerberus shared instance), not the upstream's Postgres. Postgres `pgEnum` columns are modelled as plain `string` columns cast to PHP backed enums.
- **bigint auto-increment primary keys + a public `uuid` column** (via `Domain\Shared\Traits\HasUuid`), instead of the upstream's UUID primary keys. This keeps Laravel Cashier's migrations working unmodified and matches the standard `HasUuid` pattern. Look entities up by `uuid` for public/URL use, by `id` internally.
- **Tests run on SQLite `:memory:`** (Laravel 13 default — fast, no external DB), not a dedicated `cellar_os_test` MySQL database.
- **Auth is plain Livewire/session** aligned to the DDD layout (the stock starter kit puts `User` in `app/Models`, which violates the "models live in `/domain`" rule). Auth UI/flows are queued as tasks.
- **The Company is the tenant**, not the user. `Domain\Company\Models\Company` is the Cashier `Billable` and holds the `plan`; **users are seats** (a `role` of `owner`/`manager`/`member` — see `Domain\User\Enums\Role`), and **venues belong to the company** (`company_id`). A user's venue access is **role-aware**: owners/managers see every company venue, members only the venues assigned to them via the **`user_venue`** pivot. App code resolves this through `App\Livewire\Concerns\WithTenant` (`currentCompany()`, `companyPlan()`, `accessibleVenues()`) + `Domain\Company\Repositories\CompanyRepository::getLoggedInCompany()`. Plan gating reads the **company** plan (`Plan::can(Feature)`); billing (Pricing/checkout/portal/webhook `UpdateCompanyPlanFromStripe`) is **owner-only** and acts on the Company billable. Registration creates company→owner→venue→pivot. Owners/managers manage the team at **`/team`** (invite users by email at-or-below their own role, assign venue visibility); admins manage companies/plans/teams at **`/admin/companies`**.
- **Admins are a fully separate domain** (`Domain\Admin`), table (`admins`), and auth guard (`admin`) — independent of end users. See below.
- **Supplier portal is a third separate auth domain** (`supplier` guard, `supplier_users` table, `supplier_password_reset_tokens` broker) living inside `Domain\Supplier`. A `Supplier` (the wine company) has many `supplier_users` (logins). Never mix supplier auth into the `User` or `Admin` contexts.

---

## Implemented features

All bounded contexts have a working UI + tests. Modules (each: Livewire in `app/Livewire/<Area>`, domain Actions/Repositories, feature tests, independently reviewed):

- **Auth** — login / register (captures company→venue, base currency, profession) / logout / password reset (session, DDD-aligned).
- **Dashboard** — KPI cards (bottles & inventory value, low/out-of-stock), inventory breakdowns by colour/country/region, recent orders, low-stock alerts, getting-started guide.
- **Guide** (`/guide`, public) — documentation-style site with its **own** doc layout + sticky sidenav (`layouts/guide.blade.php`), not the app shell. Each section is a real URL (`/guide/{section}`) backed by a prose partial in `resources/views/guide/sections/`; the sidenav config lives in `App\Livewire\Guide::sections()`. Covers every area + user journeys + the plan-feature matrix + a **Demo logins** page (`/guide/demo-logins`). Written for a layperson — no developer/CLI references.
- **Suppliers ("My suppliers")** — suppliers are now **tiered** (`Domain\Supplier\Enums\SupplierTier`, derived from `created_by_company_id` + `onboarded_at`): **Private** (a buyer's own off-platform record, editable only by the company that created it), **Listed** (admin-added, public/discoverable), **Onboarded** (has a portal account). A company's `/suppliers` page shows only its **connected** suppliers (`company_supplier` pivot) + a **Discover** tab to connect to public ones + "add a private supplier"; connected suppliers are allocated to venues (`supplier_venue` pivot). Buyers may edit/delete **only their own private** records (public ones are read-only). Ordering is restricted to connected suppliers. Admins promote tiers (make public / mark onboarded) in `/admin/suppliers`.
- **Supplier portal** — a third isolated auth domain (`supplier` guard) at `/supplier`: login (throttled), dashboard, **Documents** (upload portfolios/price sheets to the private disk → status **Awaiting Analysis**; download/delete own files), company **Profile**. Uses `layouts/supplier.blade.php`. Admins provision accounts under **`/admin/suppliers`** (list/create companies → `SupplierShow`: edit profile, add/remove portal users with **email invite links** via the `supplier_users` password broker, list documents, trigger **Analyse**, download). The analysis pipeline is **fully implemented**: `AnalyseSupplierDocumentJob` (`timeout` 1800s, `tries` 1 — `DB_QUEUE_RETRY_AFTER` must exceed it) drives `AwaitingAnalysis → Analysing → Analysed | Failed`, calling `Domain\Supplier\Services\DocumentAnalysisService`, which parses the document with Claude (see **Portfolio parsing** below) into the `parsed_wines` review queue and records a summary in `analysis_notes`.
- **Catalogue** — sortable/filterable product table, inline price edit, session basket (`order-basket`) that feeds Orders.
- **Inventory** — per-venue stock (active-venue selector), quantity stepper, archive/restore, file attachments (private disk + authed download). Gated: Starter+ (page), Pro+ (manual add / archive / attachments), Group (2nd+ venue).
- **Import** — CSV/Excel → column mapping → preview → import wizard with `NormaliseService` (colour/grape/region standardisation, price/vintage/format parsing, region/country geocoding for the map); remembers supplier mappings; idempotent upsert. Gated Starter+.
- **Catalogue** — **scoped to the company's connected suppliers** (`ProductRepository::search(..., supplierIds:)`); browse/filter/sort + a supplier filter + a "connect suppliers" empty state. Inline price edit / delete are allowed **only for the company's own private suppliers' wines** (public/shared catalogues are read-only). The basket and the Orders create-flow only accept wines from connected suppliers. Buyers can upload a supplier's price sheet/portfolio at `/suppliers/{uuid}/documents` (stored on the private disk, same `AnalyseSupplierDocumentJob` lifecycle as portal portfolios — buyer docs are scoped to the uploading company and never shown to the supplier portal).
- **Portfolio parsing (LLM)** — `Domain\Supplier\Services\DocumentAnalysisService` (the once-stubbed boundary, now real). Two modes (`ParseMode::forFileType`, extension-first): **tabular** (csv/xlsx — Claude derives a column **mapping** once, then the existing `Import\NormaliseService` runs per row; the mapping is also written to `suppliers.column_mapping` for the import wizard) and **document** (PDF — Claude derives a structural **recipe** from the first pages, then extracts wines per ~5-page chunk with country/region/producer **section context carried across chunks**; truncated chunks auto-split and retry). Both store the "how we parsed it" recipe in **`supplier_parse_profiles`** (reused on the supplier's next upload; **company-scoped** for buyer docs so corrections/prices never bleed across tenants, global for portal/admin docs) and the wines in **`parsed_wines`** (statuses proposed/approved/rejected + safety flags: suspicious_price, missing_price, suspected_heading, low_confidence). Humans review at `/suppliers/{uuid}/documents/{id}/review` (`DocumentReview`: per-row approve/edit/reject, approve-all, re-analyse with a model toggle, "save corrections to recipe" = `RefineParseProfileAction` folding approved examples back in). **Approving commits to the catalogue via the idempotent `UpsertProductAction` — buyers may only commit for their OWN private suppliers** (shared catalogues stay read-only; the screen is review-only there). Large PDFs (>12pp) parse a **preview** first; "Run full extraction" confirms the spend. Admin bulk-approves with flagged rows skipped. `ClaudeClient` (structured outputs, `services.anthropic` config: `ANTHROPIC_API_KEY` + `ANTHROPIC_MODEL`, default claude-opus-4-8) is the ONE place that talks to the API — tests bind `Tests\Support\FakeClaudeClient`. Manual run: `php artisan wine:parse {documentId} [--full] [--model=]`. PDF text via poppler (`pdftotext`/`pdfinfo`), baked into the **published Sail runtime `docker/8.5/Dockerfile`** (compose builds from there, NOT vendor — re-publish via `sail:publish` after Sail upgrades and re-add poppler-utils if needed).
- **Orders** — list + create (basket or manual), status lifecycle, PDF (dompdf), email to supplier (Mailpit), and **Receive → inventory** (Sent-only, no double-receive). Gated createPOs / sendPOEmail (Starter+).
- **Money** — `Domain\Shared\Support\Currency`; values display in the venue's base currency (per-line currency on orders/inventory). No conversion (matches upstream).
- **Billing** — `/pricing` plan cards, Cashier checkout (swap for existing subs), webhook plan-sync (`UpdateUserPlanFromStripe`, fail-closed without `STRIPE_WEBHOOK_SECRET`).
- **Map** — `/map` Leaflet + OpenStreetMap (tokenless) global sourcing view (excludes private suppliers' wines).
- **Admin** — separate `admin` guard at `/admin`: login (throttled), dashboard, user management (plan change, delete), enquiry review (status + delete). `auth:admin` + intrinsic guards.
- **Enquiries** — public contact form on the landing (plain `<form>` → `EnquiryController@store` → `StoreEnquiryAction`, throttled), stored in `enquiries`; reviewed at `/admin/enquiries`. The marketing **pricing** section is currently hidden behind an `@if(false)` guard in `landing.blade.php` (restore by removing the guard); the contact section took its place.

Plan gating: in-component (`Plan::can(Feature)`) + the `feature:<key>` route middleware (redirects to `pricing`); UI shows `x-upgrade-gate`.

### Demo data & E2E

`php artisan migrate:fresh --seed` (or `db:seed`, idempotent) creates a shared catalogue (3 suppliers, 10 geo-located wines), a default admin, and one demo user per plan tier showing a different journey (each with its own venues/inventory/orders). All passwords are `password`; the list is also surfaced at `/guide/demo-logins`.
- Admin: `admin@cellaros.test` (at `/admin`)
- Supplier portal (at `/supplier`): three suppliers at different journeys — `supplier@cellaros.test` (Bordeaux Imports: a 2-user team, docs awaiting + analysed), `italian-supplier@cellaros.test` (Italian Fine Wines: a doc analysing + one failed), `newworld-supplier@cellaros.test` (New World Selections: **invite pending**, no password yet)
- `free@cellaros.test` (Free) — venue only, empty/getting-started state
- `starter@cellaros.test` (Starter) — a draft + sent order, a little stock
- `demo@cellaros.test` (Pro) — full single venue: stock + orders across the lifecycle (used by E2E auth setup)
- `group@cellaros.test` (Group, **owner**) — a company with two venues (stock + orders) and a team; plus `group.member@cellaros.test` (**member**) scoped to just the Riverside venue

E2E: `npx playwright install chromium` once, then `npx playwright test` (auth setup logs in the demo user; `global-setup` seeds the dev DB — set `E2E_SKIP_SEED=1` to skip). Reports/auth state are gitignored.

## Development commands

The site is managed by the Cerberus CLI and runs in a Sail container named `cellar-os-app`.

```bash
# Lifecycle (from anywhere)
cerberus start cellar-os          # boot containers + Vite
cerberus restart cellar-os        # restart (re-patches compose, restarts Vite)
cerberus restart-vite cellar-os   # just restart Vite
cerberus logs cellar-os           # tail Vite output

# Artisan / Composer / NPM run INSIDE the container
docker exec cellar-os-app php artisan <cmd>
docker exec cellar-os-app composer <cmd>

# Or via Sail (from the project dir)
./vendor/bin/sail artisan <cmd>
./vendor/bin/sail composer <cmd>
./vendor/bin/sail npm <cmd>

# Tests & formatting — run INSIDE the container (a dependency requires PHP 8.4+,
# host PHP is 8.3, so host ./vendor/bin/{pest,pint} dies on the platform check)
docker exec cellar-os-app ./vendor/bin/pest   # unit + feature (SQLite :memory:)
docker exec cellar-os-app ./vendor/bin/pint   # PSR-12 formatting
npx playwright test               # E2E (needs `npx playwright install chromium` once)
```

- **URL:** http://cellar-os.cerberus.local
- **Mail:** captured by shared Mailpit — http://mailpit.cerberus.local/ (SMTP `host.docker.internal:1025`).
- **DB:** MySQL `cellar_os` on the shared `mysql` container.

---

## Architecture overview

Domain-Driven Design. **ALL business logic lives in `/domain`** (the `Domain\` namespace, mapped in `composer.json`). `app/` holds only the HTTP/Livewire/framework glue. There is **no `app/Models`** and **no `app/Domain`**.

Each bounded context is self-contained — `Models/`, `Actions/`, `Data/`, `Repositories/`, plus `Enums/`, `Services/`, `Jobs/` where needed.

### Bounded contexts

| Context | Responsibility | Key tables |
|---------|----------------|------------|
| `Shared` | Base classes & traits (`AbstractAction`, `AbstractData`, `HasUuid`) | — |
| `Company` | **The tenant/account**: holds the plan + Cashier billing, owns users/venues/suppliers | `companies` |
| `User` | Login seats within a company (role: owner/manager/member), profiles | `users`, `user_profiles`, `user_venue` |
| `Admin` | **Separate** back-office administrators (own guard) | `admins`, `admin_password_reset_tokens` |
| `Venue` | Trading locations owned by a company; users get access via the `user_venue` pivot | `venues` |
| `Supplier` | Wine suppliers (tiered: private/listed/onboarded) + buyer↔supplier connections + venue allocations; the supplier portal (profile, portal logins, uploaded portfolios + analysis lifecycle); import column mappings | `suppliers`, `company_supplier`, `supplier_venue`, `supplier_users`, `supplier_password_reset_tokens`, `supplier_documents` |
| `Catalogue` | Wine products with full attributes + geo | `products` |
| `Import` | Raw uploaded supplier price lists (CSV/Excel) | `raw_uploads` |
| `Order` | Purchase orders + line items (unit-based: 1 unit = 1 bottle) | `orders`, `order_items` |
| `Inventory` | Received stock per venue + file attachments | `inventory_items`, `inventory_attachments` |
| `Billing` | Plan tiers, feature gating, Stripe (Cashier) | Cashier: `subscriptions`, `subscription_items` |
| `Enquiry` | Public contact-form submissions, reviewed in admin | `enquiries` |

---

## Domain structure

```
domain/
├── Shared/
│   ├── Actions/AbstractAction.php
│   ├── Data/AbstractData.php          # extends Spatie Data, implements Wireable
│   └── Traits/HasUuid.php             # fills the `uuid` column on create
├── User/
│   ├── Models/{User,UserProfile}.php
│   ├── Data/{UserData,UserProfileData}.php
│   ├── Repositories/UserRepository.php   # getLoggedInUser()
│   ├── Actions/                       # (to build)
│   └── ...
├── Admin/
│   ├── Models/Admin.php               # Authenticatable, `admin` guard
│   ├── Data/AdminData.php
│   └── Repositories/AdminRepository.php  # getLoggedInAdmin()
├── Venue/{Models,Data,Repositories,Actions}
├── Supplier/{Models,Data,Repositories,Actions}
├── Catalogue/
│   ├── Models/Product.php
│   ├── Data/ProductData.php
│   ├── Repositories/ProductRepository.php
│   └── Enums/WineColour.php
├── Import/
│   ├── Models/RawUpload.php
│   ├── {Data,Repositories}
│   └── Services/                      # normalisation, parsing (to build)
├── Order/
│   ├── Models/{Order,OrderItem}.php
│   ├── Data/{OrderData,OrderItemData}.php
│   ├── Repositories/OrderRepository.php
│   ├── Enums/OrderStatus.php
│   ├── Services/                      # PDF generation (to build)
│   └── Jobs/                          # async email (to build)
├── Inventory/
│   ├── Models/{InventoryItem,InventoryAttachment}.php
│   ├── {Data,Repositories}
│   └── Services/
├── Billing/
│   ├── Enums/{Plan,Feature}.php
│   ├── Services/                      # Cashier wrappers (to build)
│   └── ...
└── Enquiry/
    ├── Models/Enquiry.php
    ├── Data/EnquiryData.php
    ├── Enums/EnquiryStatus.php        # New | Read | Archived
    ├── Repositories/EnquiryRepository.php
    └── Actions/{StoreEnquiryAction,MarkEnquiryStatusAction,DeleteEnquiryAction}.php
```

---

## Business logic location

| Layer | Location | Purpose |
|-------|----------|---------|
| Action | `domain/<Ctx>/Actions/` | A single write operation. One `execute()` method, accepts & returns DTOs. |
| Repository | `domain/<Ctx>/Repositories/` | Read-only queries. **Always returns DTOs**, never Eloquent models. |
| Data (DTO) | `domain/<Ctx>/Data/` | Immutable transfer objects. Extend `AbstractData`, are Livewire-`Wireable`. |
| Model | `domain/<Ctx>/Models/` | Eloquent mapping only. `$fillable`, `casts()`, `getData()`. **No business logic.** |
| Service | `domain/<Ctx>/Services/` | Wrap external integrations (Stripe, PDF, mail, parsing) only. |
| Enum | `domain/<Ctx>/Enums/` | Backed enums with helper methods. |
| Livewire | `app/Livewire/` | UI components. Use repositories/actions, **never models directly**. |
| Middleware | `app/Http/Middleware/` | `SecurityHeaders`, `EnsureFeatureAccess` (plan gating). |

---

## Key patterns

### Action

```php
declare(strict_types=1);

namespace Domain\Order\Actions;

use Domain\Order\Data\OrderData;
use Domain\Order\Models\Order;
use Domain\Shared\Actions\AbstractAction;

class CreateOrderAction extends AbstractAction
{
    public function execute(OrderData $data): OrderData
    {
        $order = Order::create([
            'supplier_id' => $data->supplier_id,
            'venue_id' => $data->venue_id,
            'status' => $data->status,
        ]);

        return $order->getData();
    }
}

// Usage:  $order = (new CreateOrderAction())->execute($data);
```

### Repository (read-only, returns DTOs)

```php
class ProductRepository
{
    public function findByUuid(string $uuid): ?ProductData
    {
        return Product::where('uuid', $uuid)->first()?->getData();
    }

    public function paginate(int $perPage = 24): LengthAwarePaginator
    {
        return Product::orderBy('wine_name')
            ->paginate($perPage)
            ->through(fn (Product $p) => $p->getData());
    }
}
```

### DTO

```php
class ProductData extends AbstractData
{
    public function __construct(
        public ?int $id,
        public ?string $uuid,
        public string $wine_name,
        public ?WineColour $colour,
        public ?CarbonImmutable $created_at = null,
    ) {}

    public static function fromModel(Product $model): self { /* ... */ }
    public function toModel(): Product { return Product::findOrFail($this->id); }
}
```

### Model

```php
class Product extends Model
{
    use HasUuid;
    use HasFactory;

    protected $fillable = ['wine_name', 'colour', /* ... */];

    protected function casts(): array
    {
        return ['grape' => 'array', 'colour' => WineColour::class, 'unit_price' => 'decimal:2'];
    }

    public function getData(): ProductData
    {
        return ProductData::fromModel($this);
    }
}
```

### Livewire component

```php
namespace App\Livewire;

class ProductList extends Component
{
    public function render()
    {
        return view('livewire.product-list', [
            'products' => (new ProductRepository())->paginate(),
        ]);
    }
}
```

### Plan gating

```php
// In a route:
Route::get('/orders', OrderIndex::class)->middleware(['auth', 'feature:createPOs']);

// In code:
$plan = (new UserRepository())->getLoggedInUser()?->plan ?? Plan::Free;
if ($plan->can(Feature::CreatePurchaseOrders)) { /* ... */ }
```

---

## UI & component library

Build pages from reusable Blade components — views should read as component trees, not walls of utility classes. Styling comes only from the theme tokens (`bg-primary`, `text-foreground`, `border-border`, `bg-sidebar`, …) defined in `resources/css/app.css`.

**Form inputs are components, never raw `<input>`.** They're "thin": only `label`/`hint` (and select's `options`/`placeholder`) are declared props — every real HTML attribute (`name`, `type`, `placeholder`, `required`, `value`, `wire:model`, …) flows through `$attributes`. `name` is read from the attribute bag to drive label/`id` association and inline `$errors` display.

```blade
<x-input.text name="wine_name" label="Wine name" wire:model="wine_name" required />
<x-input.email name="email" label="Email" wire:model="email" />
<x-input.password name="password" label="Password" wire:model="password" />   {{-- has show/hide toggle --}}
<x-input.textarea name="notes" label="Notes" wire:model="notes" rows="5" />
<x-input.select name="status" label="Status" :options="['Active' => 'Active', 'Inactive' => 'Inactive']" wire:model="status" />
<x-input.checkbox name="remember" label="Remember me" wire:model="remember" />
```

| Component | Notes |
|-----------|-------|
| `x-input.{text,email,password,textarea,select,checkbox}` | Thin field wrappers (label + control + inline error). |
| `x-input.label`, `x-input.error` | Lower-level helpers used by the field components. |
| `x-button` | `variant` (primary/secondary/outline/ghost/danger), `size` (sm/md/lg), `href` to render as `<a>`. |
| `x-card` | `title`/`subtitle` props or `<x-slot:header>` / `<x-slot:footer>`. |
| `x-badge` | `color` (gray/amber/blue/green/emerald/red/wine) — maps to `OrderStatus::getColour()`. |
| `x-alert` | `variant` (success/error/warning/info). |
| `x-stat` | KPI tile: `label`, `value`, `icon`. |
| `x-app-logo` | Brand lockup; `href`, `showText`. |
| `x-icon.*` | Lucide icons in `resources/views/components/icon/` (stroke, `currentColor`, size via `class="size-5"`). Add more from the lucide-icons skill / lucide-static CDN — never inline raw SVG. |

**Layouts** (`resources/views/layouts/`, registered as Livewire's `layouts::` namespace): `app` (authenticated shell — sidebar + topbar + theme toggle + user menu; sidebar items guard on `Route::has()` and show "soon" until their route exists) and `guest` (centered card for auth). Both apply the theme before paint to avoid a dark-mode flash.

**Livewire:** components are **class-based** in `app/Livewire/` with views in `resources/views/livewire/` (project default set via `config/livewire.php` `make_command.type = 'class'` — not Livewire 4's single-file default). Full-page components set their chrome with `#[Layout('layouts.app')]` + `#[Title('…')]`. Components call repositories/actions, never models. Auth lives in `app/Livewire/Auth/` (Login, Register, ForgotPassword, ResetPassword); registration runs through `Domain\User\Actions\RegisterUserAction`. Logout is a POST route. Guest/auth redirects are configured in `bootstrap/app.php` (`redirectGuestsTo` → login, `redirectUsersTo` → /dashboard).

## Critical rules

**DDD**
- ALL business logic in `/domain`, NEVER `/app/Domain`. ALL models in `domain/*/Models/`, NEVER `app/Models`.
- Each bounded context is self-contained. **No cross-context direct model imports** — a model holds the FK column (e.g. `supplier_id`) but does NOT define an Eloquent relation to another context's model. Reach other contexts via repositories/actions/events.
- Actions: single `execute()`, accept & return DTOs.
- Repositories: read-only, return DTOs (never Eloquent models).
- DTOs extend `AbstractData` (Spatie Laravel Data + Wireable).
- Models use `$fillable` (not `$guarded`), a `casts()` method, `getData()`, and contain no business logic.
- Services wrap external integrations only.
- Livewire components use repositories/actions, never models directly.
- **Admins are isolated**: `Domain\Admin`, `admins` table, `admin` guard. Never mix admin auth into the `User` context.

**Coding standards**
- `declare(strict_types=1);` in every PHP file. PSR-12 (run Pint).
- `snake_case` variables/properties; `camelCase` methods.
- Use Laravel collections, NOT Spatie DataCollections.
- User retrieval: `(new UserRepository())->getLoggedInUser()`, NEVER `Auth::user()` in domain code. (Admins: `(new AdminRepository())->getLoggedInAdmin()`.)
- All styling via Tailwind utilities; all icons as Blade components in `resources/views/components/icon/` (no inline SVG). Build reusable Blade components for any UI pattern used more than once.
- No `@php` blocks in Blade — use `{{ }}` and directives.
- Throw exceptions for broken state; return null only for explicitly optional data.
- `SecurityHeaders` middleware wraps HSTS in `app()->environment('production')` — never send HSTS locally (breaks `*.cerberus.local`).

---

## Testing

**Pest** (unit + feature, SQLite `:memory:`, `RefreshDatabase`):

```php
use Domain\Billing\Enums\{Plan, Feature};

it('gates purchase orders behind the starter plan', function () {
    expect(Plan::Free->can(Feature::CreatePurchaseOrders))->toBeFalse()
        ->and(Plan::Starter->can(Feature::CreatePurchaseOrders))->toBeTrue();
});
```

Factories exist for every model (`database/factories`). Cross-context FKs are left null in factories — set them explicitly in tests (`Product::factory()->create(['supplier_id' => $supplier->id])`).

**Playwright** (E2E in `tests/e2e/specs/*.spec.ts`), config in `playwright.config.ts`, base URL `http://cellar-os.cerberus.local`. `auth.setup.ts` persists a logged-in session; `global-setup.ts` is the DB-seed hook. Run `npx playwright install chromium` once before first use.

---

## Database schema

bigint PKs + unique `uuid` columns on public entities. Key points:

- `companies` — the tenant: `uuid`, `name`, `base_currency`, `plan`, + Cashier columns (`stripe_id`, `pm_type`, `pm_last_four`, `trial_ends_at`). `subscriptions.company_id` is the Cashier owner key.
- `users` — `uuid`, `company_id` (FK, cascade), `full_name`, `email`, `password` (nullable until an invited seat is activated), `role` (`owner`/`manager`/`member`). **No** `plan`/Cashier columns — those live on `companies`.
- `user_venue` — pivot granting a user access to specific venues (members are scoped to these; owners/managers see all company venues).
- `venues` — `company_id` (FK, cascade), `name`, `address`, `city`, `country`, `base_currency`.
- `admins` — separate auth table (`uuid`, `name`, `email`, `password`).
- `suppliers` — wine companies; profile columns (`address`/`city`/`postcode`/`country`/`website`); **tier** columns `created_by_company_id` (FK companies, cascadeOnDelete — set = Private) and `onboarded_at` (set = Onboarded). Tier derived in `SupplierData->tier`.
- `company_supplier` — a company's connected suppliers ("My suppliers"); `supplier_venue` — allocation of a connected supplier to a company's venues. Both queried via the DB facade (no cross-context relation).
- `supplier_users` — portal logins (`supplier` guard), FK `supplier_id`; `password` nullable until the invite link is used.
- `supplier_documents` — uploaded portfolios/sheets; the **real file** lives on the private `local` disk (`storage_path`), `status` cast to `SupplierDocumentStatus`, plus `analysis_notes` / `analysed_at`. Uploader is either a portal user (`uploaded_by_supplier_user_id`) or a buyer (`uploaded_by_company_id` + `uploaded_by_user_id` — company-scoped, never shown to the portal).
- `supplier_parse_profiles` — the learned parse "recipe" per supplier+mode (`recipe` JSON: column mapping for tabular, structure+examples for PDF); `company_id` null = global (portal/admin-learned), set = that buyer's own; `is_active` latest-wins per scope.
- `parsed_wines` — the parse review queue: `payload` (a normalised ProductData snapshot), `status` (proposed/approved/rejected), `confidence`, `flag`, `source_ref` (row/page provenance).
- `products` — full wine attributes; `grape` is JSON; `colour` cast to `WineColour`; geo `latitude`/`longitude`; indexes on `(country, region)` and `colour`.
- `orders` / `order_items` — `status` cast to `OrderStatus`; ordering is **unit-based** (`quantity_units`, 1 unit = 1 bottle); `unit_price_at_order` / `currency_at_order` snapshot the price at order time.
- `inventory_items` — unique on `(venue_id, product_id)`; archive support (`is_archived`, `archived_at`).
- `inventory_attachments` — metadata in DB, files on the local `public` disk (`storage/app/public`, swap to S3-compatible later).

---

## Adding a new feature

1. **New context:** `domain/<Context>/{Models,Actions,Data,Repositories}` (+ `Enums/Services/Jobs` as needed).
2. **Model:** `$fillable`, `casts()`, `getData()`, `HasUuid` + `HasFactory`; only same-context relations.
3. **DTO:** extend `AbstractData`; `fromModel()` + `toModel()`; `CarbonImmutable` for timestamps; type enum props.
4. **Repository:** read-only methods returning DTOs (`->through(...->getData())` for paginators).
5. **Action:** one `execute(SomeData): SomeData` per write operation.
6. **Factory:** in `database/factories`, `protected $model`, fill own columns + `uuid`, leave cross-context FKs null.
7. **Livewire + Blade:** component in `app/Livewire/`, view composed from Blade components; gate with `feature:<key>` middleware where the upstream gated it (see `Domain\Billing\Enums\Feature`).
8. **Tests:** a Pest unit/feature test; a Playwright spec for the user-facing flow.
