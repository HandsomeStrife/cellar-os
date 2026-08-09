<?php

declare(strict_types=1);

namespace App\Livewire\Catalogue;

use Domain\Billing\Enums\Feature;
use Domain\Billing\Enums\Plan;
use Domain\Catalogue\Actions\DeleteProductAction;
use Domain\Catalogue\Actions\UpdateProductPriceAction;
use Domain\Catalogue\Data\AiSearchFilterData;
use Domain\Catalogue\Data\ProductData;
use Domain\Catalogue\Enums\WineSubType;
use Domain\Catalogue\Enums\WineType;
use Domain\Catalogue\Repositories\CatalogueEnrichmentRepository;
use Domain\Catalogue\Repositories\ProductRepository;
use Domain\Catalogue\Services\AiSearchService;
use Domain\Company\Repositories\CompanyRepository;
use Domain\Order\Actions\CreateOrderAction;
use Domain\Order\Data\OrderData;
use Domain\Order\Data\OrderItemData;
use Domain\Order\Enums\OrderStatus;
use Domain\Supplier\Repositories\SupplierRepository;
use Domain\User\Repositories\UserRepository;
use Domain\Venue\Repositories\VenueRepository;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Session;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Catalogue')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $country = '';

    #[Url(history: true)]
    public string $colour = '';

    #[Url(history: true)]
    public string $sub_type = '';

    #[Url(history: true)]
    public string $supplierFilter = '';

    #[Url(history: true)]
    public string $region = '';

    #[Url(history: true)]
    public string $sub_region = '';

    #[Url(history: true)]
    public string $producer = '';

    #[Url(history: true)]
    public string $grape = '';

    #[Url(history: true)]
    public string $priceMin = '';

    #[Url(history: true)]
    public string $priceMax = '';

    #[Url(history: true)]
    public string $vintageMin = '';

    #[Url(history: true)]
    public string $vintageMax = '';

    /**
     * Filters held in the "Filters" panel (search/supplier live in the
     * always-visible toolbar and are counted separately).
     */
    private const PANEL_FILTERS = [
        'colour', 'sub_type', 'country', 'region', 'sub_region', 'producer', 'grape',
        'priceMin', 'priceMax', 'vintageMin', 'vintageMax',
    ];

    /**
     * The optional table columns a user can show/hide. Wine, price and the
     * basket actions always render.
     */
    public const COLUMNS = [
        'producer' => 'Producer',
        'supplier' => 'Supplier',
        'country' => 'Country',
        'region' => 'Region',
        'grapes' => 'Grapes',
        'colour' => 'Type',
        'vintage' => 'Vintage',
        'format' => 'Format',
    ];

    /** @var array<int, string> */
    #[Session(key: 'catalogue-columns')]
    public array $visibleColumns = ['producer', 'supplier', 'country', 'region', 'grapes', 'colour', 'vintage', 'format'];

    // AI search: a plain-English question, read into the filters below.
    public string $aiQuery = '';

    /** What the model understood, shown above the results. */
    public string $aiSummary = '';

    public bool $aiFailed = false;

    /**
     * The last interpretation, kept so the per-wine reasons can be recomputed
     * as the buyer pages through the results (it's Wireable, and costs nothing
     * to re-apply — no further model calls).
     */
    public ?AiSearchFilterData $aiFilter = null;

    // "Add to basket" panel: how many, and who else stocks this wine.
    public ?int $addingId = null;

    public bool $showAdd = false;

    public int $addQty = 1;

    // Wine detail slideover.
    public bool $showDetail = false;

    public ?int $detailId = null;

    public string $sort = ProductRepository::DEFAULT_SORT;

    public string $direction = 'asc';

    // Inline price editing
    public ?int $editingPriceId = null;

    public string $priceInput = '';

    // Order basket: product_id => quantity (bottles). Persisted across requests
    // under a shared key so the Orders module can pick it up at checkout.
    #[Session(key: 'order-basket')]
    public array $basket = [];

    public bool $showBasket = false;

    public function updated($property): void
    {
        $filters = ['search', 'supplierFilter', ...self::PANEL_FILTERS];

        if (in_array($property, $filters, true)) {
            $this->resetPage();
        }

        // Only known column keys, in the canonical order (guards a tampered payload).
        if ($property === 'visibleColumns' || str_starts_with((string) $property, 'visibleColumns.')) {
            $this->visibleColumns = array_values(array_intersect(array_keys(self::COLUMNS), $this->visibleColumns));
        }

        // Cascade: changing a broader geography clears the narrower selections
        // so the dependent dropdowns never show a stale, now-invalid value.
        if ($property === 'country') {
            $this->region = '';
            $this->sub_region = '';
        }

        if ($property === 'region') {
            $this->sub_region = '';
        }

        // Sub-types belong to one type, so changing the type invalidates any
        // sub-type already chosen.
        if ($property === 'colour') {
            $this->sub_type = '';
        }
    }

    public function resetFilters(): void
    {
        $this->reset([
            'search', 'supplierFilter', 'sort', 'direction',
            'aiQuery', 'aiSummary', 'aiFailed', 'aiFilter',
            ...self::PANEL_FILTERS,
        ]);
        $this->resetPage();
    }

    /**
     * Read a plain-English question into the ordinary filters.
     *
     * Deliberately no separate "AI results" mode: the interpretation lands in
     * the same filter fields the buyer could have set by hand, so they can see
     * exactly what it understood and correct any part of it. Throttled per
     * company, and identical questions are answered from cache.
     */
    public function runAiSearch(): void
    {
        $query = trim($this->aiQuery);

        if ($query === '') {
            return;
        }

        $companyId = (new UserRepository)->getLoggedInUser()?->company_id ?? 0;

        if (RateLimiter::tooManyAttempts('ai-search:'.$companyId, 20)) {
            $this->dispatch('toast', message: 'That\'s a lot of searches — give it a minute.');

            return;
        }

        RateLimiter::hit('ai-search:'.$companyId, 60);

        $connectedIds = (new SupplierRepository)->connectedToCompany($companyId)->pluck('id')->all();
        $repository = new ProductRepository;

        // Resolved from the container so a test can bind a fake client.
        // Most common first, so the model picks the spelling the catalogue
        // actually uses rather than a near-empty variant of it.
        $filter = app(AiSearchService::class)->interpret($query, [
            'countries' => $repository->popularValues('country', $connectedIds, 60),
            'regions' => $repository->popularValues('region', $connectedIds, 120),
        ], $companyId);

        if ($filter === null || $filter->isEmpty()) {
            // Couldn't read it (or it said nothing filterable) — fall back to
            // an ordinary text search rather than showing an error.
            $this->reset(self::PANEL_FILTERS);
            $this->search = $query;
            $this->aiSummary = '';
            $this->aiFailed = true;
            $this->aiFilter = null;
            $this->resetPage();

            return;
        }

        // A reading can be right and still find nothing — supplier lists record
        // grape so unevenly that "white Burgundy Chardonnay" can hide 1,500
        // white Burgundies. Give criteria up until something is found, and say
        // which, rather than showing an empty page.
        $service = app(AiSearchService::class);
        ['filter' => $filter, 'dropped' => $dropped] = $service->relax(
            $filter,
            fn ($candidate) => $repository->search(
                term: $candidate->search,
                country: $candidate->country,
                colour: $candidate->type,
                subType: $candidate->sub_type,
                region: $candidate->region ?: null,
                producer: $candidate->producer ?: null,
                grape: $candidate->grape ?: null,
                priceMin: $candidate->price_min,
                priceMax: $candidate->price_max,
                vintageMin: $candidate->vintage_min,
                vintageMax: $candidate->vintage_max,
                perPage: 1,
                supplierIds: $connectedIds,
            )->total(),
        );

        // Replace the panel filters wholesale: a new question means a new
        // search, not a narrowing of the last one.
        $this->reset(self::PANEL_FILTERS);

        $this->search = $filter->search;
        $this->colour = $filter->type?->value ?? '';
        $this->sub_type = $filter->sub_type?->value ?? '';
        $this->country = $filter->country;
        $this->region = $filter->region;
        $this->producer = $filter->producer;
        $this->grape = $filter->grape;
        $this->priceMin = $filter->price_min !== null ? (string) $filter->price_min : '';
        $this->priceMax = $filter->price_max !== null ? (string) $filter->price_max : '';
        $this->vintageMin = $filter->vintage_min !== null ? (string) $filter->vintage_min : '';
        $this->vintageMax = $filter->vintage_max !== null ? (string) $filter->vintage_max : '';

        $this->aiSummary = $dropped === []
            ? $filter->summary
            : $filter->summary.' Nothing matched all of that, so I set aside '
                .Str::of(implode(', ', $dropped))->replaceLast(', ', ' and ').'.';
        $this->aiFailed = false;
        $this->aiFilter = $filter;
        $this->resetPage();
    }

    public function clearAiSearch(): void
    {
        $this->reset(['aiQuery', 'aiSummary', 'aiFailed', 'aiFilter']);
        $this->reset(self::PANEL_FILTERS);
        $this->search = '';
        $this->resetPage();
    }

    /**
     * Open the detail slideover for a wine — only ever one from the company's
     * browsable (connected-supplier) catalogue.
     */
    public function showWine(int $id): void
    {
        if ($this->orderableProduct($id) === null) {
            return;
        }

        $this->detailId = $id;
        $this->showDetail = true;
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, ProductRepository::SORTABLE, true)) {
            return;
        }

        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $column;
            $this->direction = 'asc';
        }

        $this->resetPage();
    }

    public function startEditPrice(int $id, string $current): void
    {
        $this->editingPriceId = $id;
        $this->priceInput = $current;
        $this->resetValidation();
    }

    public function cancelEditPrice(): void
    {
        $this->editingPriceId = null;
        $this->priceInput = '';
    }

    public function savePrice(): void
    {
        $this->validate(['priceInput' => 'required|numeric|min:0']);
        $this->guardEditableProduct($this->editingPriceId);

        (new UpdateProductPriceAction)->execute($this->editingPriceId, (float) $this->priceInput);

        $this->editingPriceId = null;
        $this->priceInput = '';
        $this->dispatch('toast', message: 'Price updated.');
    }

    /**
     * Open the add panel for a wine: choose how many, and see which other
     * connected suppliers stock it.
     */
    public function openAdd(int $id): void
    {
        if ($this->orderableProduct($id) === null) {
            return;
        }

        $this->addingId = $id;
        $this->addQty = 1;
        $this->showAdd = true;
    }

    public function closeAdd(): void
    {
        $this->addingId = null;
        $this->addQty = 1;
        $this->showAdd = false;
    }

    /**
     * Point the open add panel at a different supplier's listing of the same
     * wine, keeping the quantity the buyer already chose.
     */
    public function switchTo(int $id): void
    {
        if ($this->orderableProduct($id) === null) {
            return;
        }

        $this->addingId = $id;
    }

    public function confirmAdd(): void
    {
        if ($this->addingId === null) {
            return;
        }

        $this->addToBasket($this->addingId, max(1, $this->addQty));
        $this->closeAdd();
    }

    /**
     * @param  int|null  $units  how many SELLING units (bottles, or cases for a
     *                           case-sold wine); one unit when not given.
     */
    public function addToBasket(int $id, ?int $units = null): void
    {
        // Never basket a wine from a supplier you're not connected to.
        $product = $this->orderableProduct($id);

        if ($product === null) {
            return;
        }

        // Case-sold wines are basketed (and stepped) a case at a time; the
        // basket always stores the bottle count so checkout stays unit-based.
        $step = $product->soldByCase() ? max(1, $product->case_size) : 1;
        $bottles = max(1, $units ?? 1) * $step;

        $this->basket[$id] = ($this->basket[$id] ?? 0) + $bottles;
        $this->dispatch('toast', message: $product->soldByCase() ? 'Cases added to basket.' : 'Added to basket.');
    }

    public function setBasketQty(int $id, int $qty): void
    {
        if ($qty <= 0 || $this->orderableProduct($id) === null) {
            unset($this->basket[$id]);

            return;
        }

        $this->basket[$id] = $qty;
    }

    /**
     * Set a case-sold line by the number of CASES (stored as bottles).
     */
    public function setBasketCases(int $id, int $cases): void
    {
        $product = $this->orderableProduct($id);

        if ($product === null || ! $product->soldByCase()) {
            return;
        }

        if ($cases <= 0) {
            unset($this->basket[$id]);

            return;
        }

        $this->basket[$id] = $cases * max(1, $product->case_size);
    }

    private function isOrderableProduct(int $id): bool
    {
        return $this->orderableProduct($id) !== null;
    }

    /**
     * The product if it belongs to one of the company's connected suppliers,
     * else null (the only wines a company may order).
     */
    private function orderableProduct(int $id): ?ProductData
    {
        $product = (new ProductRepository)->find($id);

        if ($product === null) {
            return null;
        }

        $companyId = (new UserRepository)->getLoggedInUser()?->company_id ?? 0;
        $connectedIds = (new SupplierRepository)->connectedToCompany($companyId)->pluck('id')->all();

        return in_array($product->supplier_id, $connectedIds, true) ? $product : null;
    }

    public function removeFromBasket(int $id): void
    {
        unset($this->basket[$id]);
    }

    public function clearBasket(): void
    {
        $this->basket = [];
        $this->showBasket = false;
    }

    public function deleteProduct(int $id): void
    {
        $this->guardEditableProduct($id);

        (new DeleteProductAction)->execute($id);
        unset($this->basket[$id]);
        $this->dispatch('toast', message: 'Wine removed from the catalogue.');
    }

    /**
     * A buyer may only edit/delete wines belonging to its OWN private suppliers;
     * public/shared suppliers' catalogues are read-only here.
     */
    private function guardEditableProduct(int $id): void
    {
        $companyId = (new UserRepository)->getLoggedInUser()?->company_id;
        $product = (new ProductRepository)->find($id);
        $supplier = $product?->supplier_id ? (new SupplierRepository)->find($product->supplier_id) : null;

        abort_unless(
            $companyId !== null && $supplier !== null && $supplier->created_by_company_id === $companyId,
            403
        );
    }

    /**
     * Turn the basket into draft purchase orders, one per supplier
     * (mirrors the upstream "Create N POs" flow).
     */
    public function createOrders()
    {
        abort_unless($this->plan()->can(Feature::CreatePurchaseOrders), 403);

        $repository = new ProductRepository;
        $user = (new UserRepository)->getLoggedInUser();
        $userId = $user?->id;
        $companyId = $user?->company_id ?? 0;
        $currency = (new VenueRepository)->currencyForCompany($companyId);

        // Only the company's connected suppliers can be ordered from.
        $connectedIds = (new SupplierRepository)->connectedToCompany($companyId)->pluck('id')->all();

        $groups = [];
        foreach ($this->basket as $productId => $qty) {
            $product = $repository->find((int) $productId);
            if ($product === null || ! in_array($product->supplier_id, $connectedIds, true)) {
                continue;
            }
            $groups[$product->supplier_id ?? 0][] = ['product' => $product, 'qty' => (int) $qty];
        }

        if ($groups === []) {
            return null;
        }

        $created = 0;
        foreach ($groups as $supplierId => $lines) {
            $items = array_map(fn ($line) => new OrderItemData(
                id: null,
                order_id: null,
                product_id: $line['product']->id,
                wine_name: $line['product']->wine_name,
                quantity_units: $line['qty'],
                // A POA line goes onto the order with no price — the supplier
                // quotes it back. Null is what the PO renders as "POA".
                unit_price_at_order: $line['product']->price_state->expectsPrice()
                    ? number_format((float) ($line['product']->unit_price ?? 0), 2, '.', '')
                    : null,
                currency_at_order: $currency,
                sold_by_at_order: $line['product']->sold_by->value,
                pack_size_at_order: $line['product']->soldByCase() ? $line['product']->case_size : null,
                pack_price_at_order: $line['product']->soldByCase() ? $line['product']->displayPrice() : null,
            ), $lines);

            (new CreateOrderAction)->execute(new OrderData(
                id: null,
                uuid: null,
                company_id: $user?->company_id,
                supplier_id: $supplierId ?: null,
                venue_id: null,
                created_by: $userId,
                status: OrderStatus::Draft,
                total: null,
                notes: null,
                items: $items,
            ));
            $created++;
        }

        $this->basket = [];
        $this->showBasket = false;
        $this->dispatch('toast', message: $created.' draft '.Str::plural('order', $created).' created.');

        return $this->redirect(route('orders'), navigate: true);
    }

    private function plan(): Plan
    {
        return (new CompanyRepository)->getLoggedInCompany()?->plan ?? Plan::default();
    }

    public function render()
    {
        $repository = new ProductRepository;

        // The catalogue is scoped to the wines of the company's connected suppliers.
        $companyId = (new UserRepository)->getLoggedInUser()?->company_id ?? 0;
        $connected = (new SupplierRepository)->connectedToCompany($companyId);
        $connectedIds = $connected->pluck('id')->all();

        // Optional narrowing to one connected supplier.
        $supplierFilter = (int) $this->supplierFilter;
        $supplierIds = $supplierFilter !== 0 && in_array($supplierFilter, $connectedIds, true)
            ? [$supplierFilter]
            : $connectedIds;

        $products = $repository->search(
            term: $this->search,
            country: $this->country,
            colour: WineType::tryFrom($this->colour),
            subType: WineSubType::tryFrom($this->sub_type),
            region: $this->region ?: null,
            subRegion: $this->sub_region ?: null,
            producer: $this->producer ?: null,
            grape: $this->grape ?: null,
            priceMin: $this->priceMin !== '' ? (float) $this->priceMin : null,
            priceMax: $this->priceMax !== '' ? (float) $this->priceMax : null,
            vintageMin: $this->vintageMin !== '' ? (int) $this->vintageMin : null,
            vintageMax: $this->vintageMax !== '' ? (int) $this->vintageMax : null,
            sort: $this->sort,
            direction: $this->direction,
            supplierIds: $supplierIds,
        );

        // Count of active "More filters" panel selections, for the toolbar badge.
        $filterCount = collect(self::PANEL_FILTERS)
            ->filter(fn (string $field) => trim((string) $this->{$field}) !== '')
            ->count();

        // Gap-fill missing attributes from the shared wine-facts store (grape,
        // colour, origin — never prices). Enriched values are marked in the UI
        // as populated from another vendor's data; the source is never named.
        $enriched = (new CatalogueEnrichmentRepository)->forProducts($products->items());

        // Resolve basket lines into product DTOs + line totals — only for wines
        // from connected suppliers (a tampered basket can't leak others' pricing).
        $basketLines = collect($this->basket)
            ->map(function (int $qty, int $productId) use ($repository, $connectedIds) {
                $product = $repository->find($productId);

                if ($product === null || ! in_array($product->supplier_id, $connectedIds, true)) {
                    return null;
                }

                return [
                    'product' => $product,
                    'qty' => $qty,
                    'is_case' => $product->soldByCase(),
                    'cases' => $product->soldByCase() ? intdiv($qty, max(1, $product->case_size)) : null,
                    'case_price' => $product->soldByCase() ? $product->displayPrice() : null,
                    'line_total' => (float) $product->unit_price * $qty,
                ];
            })
            ->filter()
            ->values();

        // Wines the buyer may edit/delete inline: only their own private suppliers'.
        $editableSupplierIds = $connected->filter(fn ($s) => $s->created_by_company_id === $companyId)->pluck('id')->all();

        // The wine open in the detail slideover, with its own enrichment fills
        // and supplier name (only ever a connected supplier's wine).
        $detail = null;
        $detailFill = [];
        $detailSupplier = null;
        if ($this->detailId !== null) {
            $detail = $repository->find($this->detailId);
            if ($detail === null || ! in_array($detail->supplier_id, $connectedIds, true)) {
                $detail = null;
            } else {
                $detailFill = (new CatalogueEnrichmentRepository)->forProducts([$detail])[$detail->id] ?? [];
                $detailSupplier = $connected->firstWhere('id', $detail->supplier_id);
            }
        }

        // Naming the supplier in every row is noise once you've filtered to a
        // single supplier — the whole table is theirs.
        $showSupplierColumn = in_array('supplier', $this->visibleColumns, true) && $supplierFilter === 0;

        // The wine in the add panel, plus the same wine from any OTHER
        // connected supplier — cheapest first, with the saving spelled out.
        $adding = null;
        $alternatives = collect();
        if ($this->showAdd && $this->addingId !== null) {
            $adding = $repository->find($this->addingId);

            if ($adding === null || ! in_array($adding->supplier_id, $connectedIds, true)) {
                $adding = null;
            } else {
                $alternatives = $repository->alternativesFor($adding, $connectedIds);
            }
        }

        return view('livewire.catalogue.index', [
            'adding' => $adding,
            'alternatives' => $alternatives,
            'aiSummary' => $this->aiSummary,
            // Recomputed per page from the stored interpretation — deterministic,
            // so it never contradicts the row it sits under.
            'aiReasons' => $this->aiFilter !== null
                ? app(AiSearchService::class)->reasons($this->aiFilter, $products->items())
                : [],
            // The supplier column is meaningless while filtered to one supplier,
            // so it drops out of the picker too rather than toggling nothing.
            'columns' => $supplierFilter !== 0
                ? array_diff_key(self::COLUMNS, ['supplier' => null])
                : self::COLUMNS,
            'showSupplierColumn' => $showSupplierColumn,
            'supplierNames' => $connected->pluck('name', 'id')->all(),
            'detail' => $detail,
            'detailFill' => $detailFill,
            'detailSupplier' => $detailSupplier,
            'products' => $products,
            'enriched' => $enriched,
            'countries' => $repository->countries($connectedIds),
            'regions' => $repository->regions($connectedIds, $this->country ?: null),
            'subRegions' => $repository->subRegions($connectedIds, $this->country ?: null, $this->region ?: null),
            'filterCount' => $filterCount,
            'types' => WineType::cases(),
            'subTypes' => $this->colour !== '' && WineType::tryFrom($this->colour) !== null
                ? WineSubType::forType(WineType::from($this->colour))
                : [],
            'connectedSuppliers' => $connected,
            'hasConnections' => $connected->isNotEmpty(),
            'editableSupplierIds' => $editableSupplierIds,
            'basketLines' => $basketLines,
            'basketTotal' => $basketLines->sum('line_total'),
            'basketCount' => $basketLines->count(),
            'canCreateOrders' => $this->plan()->can(Feature::CreatePurchaseOrders),
            'currency' => (new VenueRepository)->currencyForCompany($companyId),
        ]);
    }
}
