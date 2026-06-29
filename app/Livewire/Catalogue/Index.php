<?php

declare(strict_types=1);

namespace App\Livewire\Catalogue;

use Domain\Billing\Enums\Feature;
use Domain\Billing\Enums\Plan;
use Domain\Catalogue\Actions\DeleteProductAction;
use Domain\Catalogue\Actions\UpdateProductPriceAction;
use Domain\Catalogue\Data\ProductData;
use Domain\Catalogue\Enums\WineColour;
use Domain\Catalogue\Repositories\LwinRepository;
use Domain\Catalogue\Repositories\ProductRepository;
use Domain\Catalogue\Repositories\WineFactRepository;
use Domain\Catalogue\Support\WineIdentity;
use Domain\Company\Repositories\CompanyRepository;
use Domain\Order\Actions\CreateOrderAction;
use Domain\Order\Data\OrderData;
use Domain\Order\Data\OrderItemData;
use Domain\Order\Enums\OrderStatus;
use Domain\Supplier\Repositories\SupplierRepository;
use Domain\User\Repositories\UserRepository;
use Domain\Venue\Repositories\VenueRepository;
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
     * Filters held in the "More filters" panel (search/colour/supplier live in
     * the always-visible toolbar and are counted separately).
     */
    private const PANEL_FILTERS = [
        'country', 'region', 'sub_region', 'producer', 'grape',
        'priceMin', 'priceMax', 'vintageMin', 'vintageMax',
    ];

    public string $sort = 'wine_name';

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
        $filters = ['search', 'colour', 'supplierFilter', ...self::PANEL_FILTERS];

        if (in_array($property, $filters, true)) {
            $this->resetPage();
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
    }

    public function resetFilters(): void
    {
        $this->reset([
            'search', 'colour', 'supplierFilter', ...self::PANEL_FILTERS,
        ]);
        $this->resetPage();
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

    public function addToBasket(int $id): void
    {
        // Never basket a wine from a supplier you're not connected to.
        $product = $this->orderableProduct($id);

        if ($product === null) {
            return;
        }

        // Case-sold wines are basketed (and stepped) a case at a time; the
        // basket always stores the bottle count so checkout stays unit-based.
        $step = $product->soldByCase() ? max(1, $product->case_size) : 1;

        $this->basket[$id] = ($this->basket[$id] ?? 0) + $step;
        $this->dispatch('toast', message: $product->soldByCase() ? 'Case added to basket.' : 'Added to basket.');
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
                unit_price_at_order: number_format((float) ($line['product']->unit_price ?? 0), 2, '.', ''),
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
        return (new CompanyRepository)->getLoggedInCompany()?->plan ?? Plan::Free;
    }

    /**
     * For the page's products, fill missing grape/colour/country/region from
     * the shared wine-facts store. Returns product_id => [field => value].
     *
     * @param  array<int, ProductData>  $products
     * @return array<int, array<string, mixed>>
     */
    private function enrichments(array $products): array
    {
        $keys = [];
        foreach ($products as $product) {
            $key = WineIdentity::keyFor($product->producer, $product->wine_name);
            if ($key !== null) {
                $keys[$product->id] = $key;
            }
        }

        $facts = (new WineFactRepository)->forIdentities(array_values($keys));
        $lwins = (new LwinRepository)->forProducts(array_map(fn ($p) => $p->id, $products));

        $enriched = [];
        foreach ($products as $product) {
            $fill = [];

            // The supplier's OWN data always wins — enrichment only ever fills
            // gaps. LWIN reference data (curated) fills first; other vendors'
            // facts fill what remains. Each fill carries its source so the UI
            // can say where the value came from.
            $lwin = $lwins[$product->id] ?? null;
            if ($lwin !== null) {
                if ($product->colour === null && $lwin->colour !== null) {
                    $fill['colour'] = ['value' => $lwin->colour, 'source' => 'lwin'];
                }
                if (($product->country ?? '') === '' && ($lwin->country ?? '') !== '') {
                    $fill['country'] = ['value' => $lwin->country, 'source' => 'lwin'];
                }
                if (($product->region ?? '') === '' && ($lwin->region ?? '') !== '') {
                    $fill['region'] = ['value' => $lwin->region, 'source' => 'lwin'];
                }
            }

            $fact = $facts[$keys[$product->id] ?? ''] ?? null;
            if ($fact !== null) {
                // Contested fields (suppliers disagree) are withheld entirely.
                $usable = fn (string $field) => ! in_array($field, $fact->conflicted_fields, true);

                if (($product->grape ?? []) === [] && ($fact->grape ?? []) !== [] && $usable('grape')) {
                    $fill['grape'] = ['value' => $fact->grape, 'source' => 'vendor'];
                }
                if (! isset($fill['colour']) && $product->colour === null && $fact->colour !== null && $usable('colour')) {
                    $fill['colour'] = ['value' => $fact->colour, 'source' => 'vendor'];
                }
                if (! isset($fill['country']) && ($product->country ?? '') === '' && ($fact->country ?? '') !== '' && $usable('country')) {
                    $fill['country'] = ['value' => $fact->country, 'source' => 'vendor'];
                }
                if (! isset($fill['region']) && ($product->region ?? '') === '' && ($fact->region ?? '') !== '' && $usable('region')) {
                    $fill['region'] = ['value' => $fact->region, 'source' => 'vendor'];
                }
            }

            if ($fill !== []) {
                $enriched[$product->id] = $fill;
            }
        }

        return $enriched;
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
            colour: WineColour::tryFrom($this->colour),
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
        $enriched = $this->enrichments($products->items());

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

        return view('livewire.catalogue.index', [
            'products' => $products,
            'enriched' => $enriched,
            'countries' => $repository->countries($connectedIds),
            'regions' => $repository->regions($connectedIds, $this->country ?: null),
            'subRegions' => $repository->subRegions($connectedIds, $this->country ?: null, $this->region ?: null),
            'filterCount' => $filterCount,
            'colours' => WineColour::cases(),
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
