<?php

declare(strict_types=1);

namespace App\Livewire\Orders;

use App\Livewire\Concerns\WithTenant;
use App\Mail\PurchaseOrderMail;
use Domain\Billing\Enums\Feature;
use Domain\Billing\Enums\Plan;
use Domain\Inventory\Actions\AddInventoryItemAction;
use Domain\Order\Actions\DeleteOrderAction;
use Domain\Order\Actions\UpdateOrderStatusAction;
use Domain\Order\Data\OrderData;
use Domain\Order\Enums\OrderStatus;
use Domain\Order\Repositories\OrderRepository;
use Domain\Order\Services\OrderPdfService;
use Domain\Supplier\Repositories\SupplierRepository;
use Domain\Venue\Repositories\VenueRepository;
use Illuminate\Support\Facades\Mail;
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

    public ?int $viewingId = null;

    private ?Plan $memoPlan = null;

    private function plan(): Plan
    {
        return $this->memoPlan ??= $this->companyPlan();
    }

    private function entitled(): bool
    {
        return $this->plan()->can(Feature::CreatePurchaseOrders);
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

        return view('livewire.orders.index', [
            'entitled' => $entitled,
            'canEmail' => $this->plan()->can(Feature::SendPurchaseOrderEmail),
            'currency' => (new VenueRepository)->currencyForCompany($companyId),
            'orders' => $orders,
            'viewing' => $viewing,
            'statuses' => OrderStatus::cases(),
            'suppliers' => (new SupplierRepository)->connectedToCompany($companyId),
            'venues' => $this->accessibleVenues(),
        ]);
    }
}
