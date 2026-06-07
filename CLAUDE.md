# CellarOS

> The operating system for the modern wine trade — inventory, suppliers, purchase orders, supplier price-list imports, and a global sourcing map.

This is a Laravel + TALL-stack (Tailwind, Alpine, Livewire, Laravel) re-implementation of the original CellarOS (a React/Express/Drizzle/Postgres app, kept as a functional reference at `https://github.com/HandsomeStrife/CellarOS`). It follows a strict Domain-Driven Design layout — all business logic lives in `/domain`.

---

## Version notes / deliberate choices

These differ from the generic `new-laravel-site` scaffold baseline; reasons recorded so future sessions don't "fix" them:

- **Laravel 13.14** (skill baseline says "12+"). Latest stable. `pestphp/pest-plugin-laravel` only resolves on Laravel 13 with Composer's `-W` flag — keep that in mind when adding test deps.
- **PHP 8.5** in the Sail container; codebase requires `^8.3`.
- **Tailwind v4** via `@tailwindcss/vite` (ships with Laravel 13). Theme defined as CSS variables in `resources/css/app.css` (no `tailwind.config.js`).
- **MySQL** (Cerberus shared instance), not the upstream's Postgres. Postgres `pgEnum` columns are modelled as plain `string` columns cast to PHP backed enums.
- **bigint auto-increment primary keys + a public `uuid` column** (via `Domain\Shared\Traits\HasUuid`), instead of the upstream's UUID primary keys. This keeps Laravel Cashier's migrations working unmodified and matches the standard `HasUuid` pattern. Look entities up by `uuid` for public/URL use, by `id` internally.
- **Tests run on SQLite `:memory:`** (Laravel 13 default — fast, no external DB), not a dedicated `cellar_os_test` MySQL database.
- **Auth is plain Livewire/session** aligned to the DDD layout (the stock starter kit puts `User` in `app/Models`, which violates the "models live in `/domain`" rule). Auth UI/flows are queued as tasks.
- **Admins are a fully separate domain** (`Domain\Admin`), table (`admins`), and auth guard (`admin`) — independent of end users. See below.

---

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

# Tests & formatting (host PHP is fine for these)
./vendor/bin/pest                 # unit + feature (SQLite :memory:)
./vendor/bin/pint                 # PSR-12 formatting
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
| `User` | End-user accounts, profiles, plan tier | `users`, `user_profiles` |
| `Admin` | **Separate** back-office administrators (own guard) | `admins`, `admin_password_reset_tokens` |
| `Venue` | Venues/locations owned by users | `venues` |
| `Supplier` | Wine suppliers + their import column mappings | `suppliers` |
| `Catalogue` | Wine products with full attributes + geo | `products` |
| `Import` | Raw uploaded supplier price lists (CSV/Excel) | `raw_uploads` |
| `Order` | Purchase orders + line items (unit-based: 1 unit = 1 bottle) | `orders`, `order_items` |
| `Inventory` | Received stock per venue + file attachments | `inventory_items`, `inventory_attachments` |
| `Billing` | Plan tiers, feature gating, Stripe (Cashier) | Cashier: `subscriptions`, `subscription_items` |

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
└── Billing/
    ├── Enums/{Plan,Feature}.php
    ├── Services/                      # Cashier wrappers (to build)
    └── ...
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

- `users` — `uuid`, `full_name`, `email`, `password`, `role`, `plan` (+ Cashier columns: `stripe_id`, `pm_type`, `pm_last_four`, `trial_ends_at`).
- `admins` — separate auth table (`uuid`, `name`, `email`, `password`).
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
