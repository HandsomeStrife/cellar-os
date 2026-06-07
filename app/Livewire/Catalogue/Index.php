<?php

declare(strict_types=1);

namespace App\Livewire\Catalogue;

use Domain\Catalogue\Actions\UpdateProductPriceAction;
use Domain\Catalogue\Enums\WineColour;
use Domain\Catalogue\Repositories\ProductRepository;
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

    // Order basket: product_id => quantity (bottles). Persisted across requests.
    #[Session]
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
        ]);
    }
}
