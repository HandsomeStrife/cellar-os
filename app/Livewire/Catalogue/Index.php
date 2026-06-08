<?php

declare(strict_types=1);

namespace App\Livewire\Catalogue;

use Domain\Billing\Enums\Feature;
use Domain\Billing\Enums\Plan;
use Domain\Catalogue\Actions\DeleteProductAction;
use Domain\Catalogue\Actions\UpdateProductPriceAction;
use Domain\Catalogue\Enums\WineColour;
use Domain\Catalogue\Repositories\ProductRepository;
use Domain\Order\Actions\CreateOrderAction;
use Domain\Order\Data\OrderData;
use Domain\Order\Data\OrderItemData;
use Domain\Order\Enums\OrderStatus;
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
        if (in_array($property, ['search', 'country', 'colour'], true)) {
            $this->resetPage();
        }
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

        (new UpdateProductPriceAction)->execute($this->editingPriceId, (float) $this->priceInput);

        $this->editingPriceId = null;
        $this->priceInput = '';
        $this->dispatch('toast', message: 'Price updated.');
    }

    public function addToBasket(int $id): void
    {
        $this->basket[$id] = ($this->basket[$id] ?? 0) + 1;
        $this->dispatch('toast', message: 'Added to basket.');
    }

    public function setBasketQty(int $id, int $qty): void
    {
        if ($qty <= 0) {
            unset($this->basket[$id]);

            return;
        }

        $this->basket[$id] = $qty;
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
        (new DeleteProductAction)->execute($id);
        unset($this->basket[$id]);
        $this->dispatch('toast', message: 'Wine removed from the catalogue.');
    }

    /**
     * Turn the basket into draft purchase orders, one per supplier
     * (mirrors the upstream "Create N POs" flow).
     */
    public function createOrders()
    {
        abort_unless($this->plan()->can(Feature::CreatePurchaseOrders), 403);

        $repository = new ProductRepository;
        $userId = (new UserRepository)->getLoggedInUser()?->id;
        $currency = (new VenueRepository)->currencyForUser($userId ?? 0);

        $groups = [];
        foreach ($this->basket as $productId => $qty) {
            $product = $repository->find((int) $productId);
            if ($product === null) {
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
            ), $lines);

            (new CreateOrderAction)->execute(new OrderData(
                id: null,
                uuid: null,
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
        return (new UserRepository)->getLoggedInUser()?->plan ?? Plan::Free;
    }

    public function render()
    {
        $repository = new ProductRepository;

        $products = $repository->search(
            term: $this->search,
            country: $this->country,
            colour: WineColour::tryFrom($this->colour),
            sort: $this->sort,
            direction: $this->direction,
        );

        // Resolve basket lines into product DTOs + line totals.
        $basketLines = collect($this->basket)
            ->map(function (int $qty, int $productId) use ($repository) {
                $product = $repository->find($productId);

                if ($product === null) {
                    return null;
                }

                return [
                    'product' => $product,
                    'qty' => $qty,
                    'line_total' => (float) $product->unit_price * $qty,
                ];
            })
            ->filter()
            ->values();

        return view('livewire.catalogue.index', [
            'products' => $products,
            'countries' => $repository->countries(),
            'colours' => WineColour::cases(),
            'basketLines' => $basketLines,
            'basketTotal' => $basketLines->sum('line_total'),
            'basketCount' => $basketLines->count(),
            'canCreateOrders' => $this->plan()->can(Feature::CreatePurchaseOrders),
            'currency' => (new VenueRepository)->currencyForUser((new UserRepository)->getLoggedInUser()?->id ?? 0),
        ]);
    }
}
