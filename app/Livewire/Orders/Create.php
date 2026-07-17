<?php

declare(strict_types=1);

namespace App\Livewire\Orders;

use App\Livewire\Concerns\WithTenant;
use Domain\Billing\Enums\Feature;
use Domain\Billing\Enums\Plan;
use Domain\Catalogue\Repositories\ProductRepository;
use Domain\Order\Actions\CreateOrderAction;
use Domain\Order\Data\OrderData;
use Domain\Order\Data\OrderItemData;
use Domain\Order\Enums\OrderStatus;
use Domain\Supplier\Repositories\SupplierRepository;
use Domain\Venue\Repositories\VenueRepository;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Full-page purchase-order composer. Building an order is the product's core
 * money workflow — it gets a page with room for the lines and a running
 * total, not a small modal.
 */
#[Layout('layouts.app')]
#[Title('New order')]
class Create extends Component
{
    use WithTenant;

    public ?int $supplierId = null;

    public ?int $venueId = null;

    public string $notes = '';

    public string $productSearch = '';

    /** @var array<int, array{product_id: int, wine_name: string, unit_price: string, quantity: int, sold_by: string, case_size: int}> */
    public array $lines = [];

    private ?Plan $memoPlan = null;

    public function mount(): void
    {
        abort_unless($this->entitled(), 403);

        // Receiving needs a venue eventually — when there's only one, assume it.
        $venues = $this->accessibleVenues();
        if ($venues->count() === 1) {
            $this->venueId = $venues->first()->id;
        }

        // Pre-fill from the catalogue basket if present — only connected wines.
        $basket = session('order-basket', []);

        if (is_array($basket) && $basket !== []) {
            $productRepo = new ProductRepository;
            $connected = $this->connectedSupplierIds();

            foreach ($basket as $productId => $qty) {
                $product = $productRepo->find((int) $productId);

                if ($product !== null && in_array($product->supplier_id, $connected, true)) {
                    $this->lines[] = [
                        'product_id' => $product->id,
                        'wine_name' => $product->wine_name,
                        'unit_price' => $product->unit_price ?? '0.00',
                        'quantity' => (int) $qty,
                        'sold_by' => $product->sold_by->value,
                        'case_size' => $product->case_size,
                    ];
                }
            }
        }
    }

    private function plan(): Plan
    {
        return $this->memoPlan ??= $this->companyPlan();
    }

    private function entitled(): bool
    {
        return $this->plan()->can(Feature::CreatePurchaseOrders);
    }

    /**
     * Supplier ids the company is connected to (the only wines it may order).
     *
     * @return array<int, int>
     */
    private function connectedSupplierIds(): array
    {
        return (new SupplierRepository)->connectedToCompany($this->currentUser()?->company_id ?? 0)
            ->pluck('id')->all();
    }

    public function addLine(int $productId): void
    {
        $product = (new ProductRepository)->find($productId);

        if ($product === null || ! in_array($product->supplier_id, $this->connectedSupplierIds(), true)) {
            return;
        }

        // Case-sold wines are added (and stepped) a case at a time.
        $step = $product->soldByCase() ? max(1, $product->case_size) : 1;

        foreach ($this->lines as $i => $line) {
            if ($line['product_id'] === $productId) {
                $this->lines[$i]['quantity'] += $step;

                return;
            }
        }

        $this->lines[] = [
            'product_id' => $product->id,
            'wine_name' => $product->wine_name,
            'unit_price' => $product->unit_price ?? '0.00',
            'quantity' => $step,
            'sold_by' => $product->sold_by->value,
            'case_size' => $product->case_size,
        ];
    }

    public function setLineQty(int $index, int $qty): void
    {
        if (! isset($this->lines[$index])) {
            return;
        }

        if ($qty <= 0) {
            unset($this->lines[$index]);
            $this->lines = array_values($this->lines);

            return;
        }

        $this->lines[$index]['quantity'] = $qty;
    }

    /**
     * Set a case-sold line by the number of CASES (stored as bottles).
     */
    public function setLineCases(int $index, int $cases): void
    {
        if (! isset($this->lines[$index])) {
            return;
        }

        if ($cases <= 0) {
            unset($this->lines[$index]);
            $this->lines = array_values($this->lines);

            return;
        }

        $this->lines[$index]['quantity'] = $cases * max(1, (int) ($this->lines[$index]['case_size'] ?? 1));
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function createOrder()
    {
        $user = $this->currentUser();
        $currency = (new VenueRepository)->currencyForCompany($user?->company_id ?? 0);

        $this->validate([
            'supplierId' => 'required|integer|exists:suppliers,id',
            // Venue (if any) must be one the current user can access.
            'venueId' => ['nullable', 'integer', Rule::in($this->accessibleVenueIds())],
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => 'required|integer|exists:products,id',
            'lines.*.quantity' => 'required|integer|min:1',
            'lines.*.unit_price' => 'required|numeric|min:0',
        ], [], ['lines' => 'order lines']);

        // You can only order from a supplier your company is connected to.
        abort_unless(
            (new SupplierRepository)->isConnectedToCompany($this->supplierId, $user?->company_id ?? 0),
            403
        );

        // Snapshot the case framing from the authoritative product at order time.
        $productRepo = new ProductRepository;
        $items = array_map(function ($line) use ($currency, $productRepo) {
            $product = $productRepo->find((int) $line['product_id']);
            $isCase = $product !== null && $product->soldByCase();

            return new OrderItemData(
                id: null,
                order_id: null,
                product_id: $line['product_id'],
                wine_name: $line['wine_name'],
                quantity_units: (int) $line['quantity'],
                unit_price_at_order: number_format((float) $line['unit_price'], 2, '.', ''),
                currency_at_order: $currency,
                sold_by_at_order: $product?->sold_by->value ?? 'bottle',
                pack_size_at_order: $isCase ? $product->case_size : null,
                pack_price_at_order: $isCase ? $product->displayPrice() : null,
            );
        }, $this->lines);

        $order = (new CreateOrderAction)->execute(new OrderData(
            id: null,
            uuid: null,
            company_id: $user?->company_id,
            supplier_id: $this->supplierId,
            venue_id: $this->venueId,
            created_by: $user?->id,
            status: OrderStatus::Draft,
            total: null,
            notes: $this->notes !== '' ? $this->notes : null,
            items: $items,
        ));

        session()->forget('order-basket');
        $this->dispatch('toast', message: 'Order '.$order->displayNumber().' created.');

        return $this->redirect(route('orders'), navigate: true);
    }

    public function render()
    {
        $companyId = $this->currentUser()?->company_id ?? 0;

        $productOptions = [];

        if ($this->productSearch !== '') {
            // The product picker only offers wines from connected suppliers.
            $productOptions = (new ProductRepository)->search(term: $this->productSearch, sort: 'wine_name', perPage: 25, supplierIds: $this->connectedSupplierIds())
                ->getCollection()
                ->mapWithKeys(fn ($p) => [$p->id => $p->wine_name.($p->vintage ? " ({$p->vintage})" : '')])
                ->all();
        }

        return view('livewire.orders.create', [
            'currency' => (new VenueRepository)->currencyForCompany($companyId),
            'suppliers' => (new SupplierRepository)->connectedToCompany($companyId),
            'venues' => $this->accessibleVenues(),
            'productOptions' => $productOptions,
            'linesTotal' => collect($this->lines)->sum(fn ($l) => $l['quantity'] * (float) $l['unit_price']),
        ]);
    }
}
