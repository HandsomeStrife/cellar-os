<?php

declare(strict_types=1);

namespace App\Livewire\Orders;

use App\Livewire\Concerns\WithTenant;
use App\Mail\PurchaseOrderMail;
use Domain\Billing\Enums\Feature;
use Domain\Billing\Enums\Plan;
use Domain\Catalogue\Repositories\ProductRepository;
use Domain\Inventory\Actions\AddInventoryItemAction;
use Domain\Order\Actions\CreateOrderAction;
use Domain\Order\Actions\DeleteOrderAction;
use Domain\Order\Actions\UpdateOrderStatusAction;
use Domain\Order\Data\OrderData;
use Domain\Order\Data\OrderItemData;
use Domain\Order\Enums\OrderStatus;
use Domain\Order\Repositories\OrderRepository;
use Domain\Order\Services\OrderPdfService;
use Domain\Supplier\Repositories\SupplierRepository;
use Domain\Venue\Repositories\VenueRepository;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Orders')]
class Index extends Component
{
    use WithPagination;
    use WithTenant;

    public string $statusFilter = '';

    public bool $showCreate = false;

    public ?int $viewingId = null;

    // Create form
    public ?int $supplierId = null;

    public ?int $venueId = null;

    public string $notes = '';

    public string $productSearch = '';

    /** @var array<int, array{product_id: int, wine_name: string, unit_price: string, quantity: int}> */
    public array $lines = [];

    private ?Plan $memoPlan = null;

    private function plan(): Plan
    {
        return $this->memoPlan ??= $this->companyPlan();
    }

    private function entitled(): bool
    {
        return $this->plan()->can(Feature::CreatePurchaseOrders);
    }

    public function openCreate(): void
    {
        abort_unless($this->entitled(), 403);

        $this->reset(['supplierId', 'venueId', 'notes', 'productSearch', 'lines']);

        // Pre-fill from the catalogue basket if present.
        $basket = session('order-basket', []);

        if (is_array($basket) && $basket !== []) {
            $productRepo = new ProductRepository;

            foreach ($basket as $productId => $qty) {
                $product = $productRepo->find((int) $productId);

                if ($product !== null) {
                    $this->lines[] = [
                        'product_id' => $product->id,
                        'wine_name' => $product->wine_name,
                        'unit_price' => $product->unit_price ?? '0.00',
                        'quantity' => (int) $qty,
                    ];
                }
            }
        }

        $this->showCreate = true;
    }

    public function addLine(int $productId): void
    {
        abort_unless($this->entitled(), 403);

        $product = (new ProductRepository)->find($productId);

        if ($product === null) {
            return;
        }

        foreach ($this->lines as $i => $line) {
            if ($line['product_id'] === $productId) {
                $this->lines[$i]['quantity']++;

                return;
            }
        }

        $this->lines[] = [
            'product_id' => $product->id,
            'wine_name' => $product->wine_name,
            'unit_price' => $product->unit_price ?? '0.00',
            'quantity' => 1,
        ];
    }

    public function setLineQty(int $index, int $qty): void
    {
        abort_unless($this->entitled(), 403);

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

    public function removeLine(int $index): void
    {
        abort_unless($this->entitled(), 403);

        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function createOrder(): void
    {
        abort_unless($this->entitled(), 403);

        $user = $this->currentUser();
        $userId = $user?->id;
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

        $items = array_map(fn ($line) => new OrderItemData(
            id: null,
            order_id: null,
            product_id: $line['product_id'],
            wine_name: $line['wine_name'],
            quantity_units: (int) $line['quantity'],
            unit_price_at_order: number_format((float) $line['unit_price'], 2, '.', ''),
            currency_at_order: $currency,
        ), $this->lines);

        (new CreateOrderAction)->execute(new OrderData(
            id: null,
            uuid: null,
            company_id: $user?->company_id,
            supplier_id: $this->supplierId,
            venue_id: $this->venueId,
            created_by: $userId,
            status: OrderStatus::Draft,
            total: null,
            notes: $this->notes !== '' ? $this->notes : null,
            items: $items,
        ));

        session()->forget('order-basket');
        $this->reset(['showCreate', 'supplierId', 'venueId', 'notes', 'productSearch', 'lines']);
        $this->resetPage();
        $this->dispatch('toast', message: 'Order created.');
    }

    public function setStatus(int $id, string $status): void
    {
        abort_unless($this->entitled(), 403);
        $this->guardOwnsOrder($id);

        $enum = OrderStatus::tryFrom($status);
        abort_if($enum === null, 422);

        (new UpdateOrderStatusAction)->execute($id, $enum);
        $this->dispatch('toast', message: 'Status updated.');
    }

    public function receive(int $id): void
    {
        abort_unless($this->entitled(), 403);

        $order = $this->guardOwnsOrder($id);

        // Only a Sent order can be received — prevents double-receiving (which
        // would top-up inventory twice).
        abort_unless($order->status === OrderStatus::Sent, 422);

        if ($order->venue_id === null) {
            $this->dispatch('toast', message: 'Assign a venue to this order before receiving it.');

            return;
        }

        // Defence: the venue must be one the current user can access.
        $ownsVenue = $this->accessibleVenues()
            ->contains(fn ($venue) => $venue->id === $order->venue_id);
        abort_unless($ownsVenue, 403);

        $addStock = new AddInventoryItemAction;

        foreach ($order->items as $item) {
            if ($item->product_id === null) {
                continue;
            }

            $addStock->execute(
                venueId: $order->venue_id,
                productId: $item->product_id,
                quantity: $item->quantity_units,
                price: (float) $item->unit_price_at_order,
                currency: $item->currency_at_order,
            );
        }

        (new UpdateOrderStatusAction)->execute($id, OrderStatus::Received);
        $this->dispatch('toast', message: 'Order received into inventory.');
    }

    public function deleteOrder(int $id): void
    {
        abort_unless($this->entitled(), 403);
        $this->guardOwnsOrder($id);

        (new DeleteOrderAction)->execute($id);
        $this->viewingId = null;
        $this->dispatch('toast', message: 'Order deleted.');
    }

    public function sendEmail(int $id): void
    {
        abort_unless($this->plan()->can(Feature::SendPurchaseOrderEmail), 403);

        $order = $this->guardOwnsOrder($id);

        $supplier = $order->supplier_id ? (new SupplierRepository)->find($order->supplier_id) : null;

        if ($supplier?->email === null) {
            $this->dispatch('toast', message: 'That supplier has no email address.');

            return;
        }

        $venue = $order->venue_id ? (new VenueRepository)->find($order->venue_id) : null;
        $pdf = (new OrderPdfService)->generate($order, $supplier, $venue)->output();

        Mail::to($supplier->email)->send(new PurchaseOrderMail($order, $pdf, $supplier->name));

        // Only advance open orders to Sent; don't regress terminal states.
        if (in_array($order->status, [OrderStatus::Draft, OrderStatus::Pending], true)) {
            (new UpdateOrderStatusAction)->execute($id, OrderStatus::Sent);
        }

        $this->dispatch('toast', message: 'Order emailed to '.$supplier->email.'.');
    }

    /**
     * Ensure the order belongs to the current user's company (tenant guard).
     */
    private function guardOwnsOrder(int $id): OrderData
    {
        $companyId = $this->currentUser()?->company_id;
        $order = $companyId ? (new OrderRepository)->findForCompany($id, $companyId) : null;
        abort_if($order === null, 403);

        return $order;
    }

    public function render()
    {
        $entitled = $this->entitled();
        $companyId = $this->currentUser()?->company_id ?? 0;
        $orders = null;
        $viewing = null;

        if ($entitled) {
            $repo = new OrderRepository;
            $status = OrderStatus::tryFrom($this->statusFilter);
            $orders = $status !== null ? $repo->byStatus($companyId, $status) : $repo->paginate($companyId);

            if ($this->viewingId !== null) {
                $viewing = $repo->findForCompany($this->viewingId, $companyId);
            }
        }

        $productOptions = [];

        if ($entitled && $this->showCreate) {
            $productOptions = (new ProductRepository)->search(term: $this->productSearch, perPage: 25)
                ->getCollection()
                ->mapWithKeys(fn ($p) => [$p->id => $p->wine_name.($p->vintage ? " ({$p->vintage})" : '')])
                ->all();
        }

        $user = $this->currentUser();
        $venues = $this->accessibleVenues();

        return view('livewire.orders.index', [
            'entitled' => $entitled,
            'canEmail' => $this->plan()->can(Feature::SendPurchaseOrderEmail),
            'currency' => (new VenueRepository)->currencyForCompany($user?->company_id ?? 0),
            'orders' => $orders,
            'viewing' => $viewing,
            'statuses' => OrderStatus::cases(),
            // Only the company's connected suppliers can be ordered from.
            'suppliers' => (new SupplierRepository)->connectedToCompany($companyId),
            'venues' => $venues,
            'productOptions' => $productOptions,
            'linesTotal' => collect($this->lines)->sum(fn ($l) => $l['quantity'] * (float) $l['unit_price']),
        ]);
    }
}
