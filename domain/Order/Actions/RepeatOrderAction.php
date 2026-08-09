<?php

declare(strict_types=1);

namespace Domain\Order\Actions;

use Domain\Catalogue\Repositories\ProductRepository;
use Domain\Order\Data\OrderData;
use Domain\Order\Data\OrderItemData;
use Domain\Order\Enums\OrderStatus;
use Domain\Order\Repositories\OrderRepository;
use Domain\Shared\Actions\AbstractAction;

/**
 * Raises a fresh DRAFT order with the same wines and quantities as an existing
 * one — the standard "same again" a venue does every week.
 *
 * Prices are re-read from the catalogue rather than copied from the old order.
 * A purchase order the buyer sends must say what the wine costs TODAY; a
 * three-month-old snapshot would be quietly wrong. Lines whose price has moved,
 * that have become POA, or whose wine is no longer listed are reported back so
 * the buyer reviews the draft rather than being surprised after sending.
 *
 * Delisted wines are kept on the draft at their old price and flagged, never
 * silently dropped: the buyer decides whether to remove them or ask.
 */
class RepeatOrderAction extends AbstractAction
{
    public function __construct(
        private ProductRepository $products = new ProductRepository,
        private OrderRepository $orders = new OrderRepository,
    ) {}

    /**
     * The company is required, not optional: OrderRepository deliberately has
     * no unscoped lookup so an order can never be reached across tenants.
     *
     * @return array{order: OrderData, changes: array<int, array{wine_name: string, change: string}>}
     */
    public function execute(int $orderId, int $companyId, ?int $createdBy = null): array
    {
        $source = $this->orders->findForCompany($orderId, $companyId);

        if ($source === null || $source->items === []) {
            throw new \RuntimeException('That order has nothing to repeat.');
        }

        $items = [];
        $changes = [];

        foreach ($source->items as $line) {
            $product = $line->product_id !== null ? $this->products->find($line->product_id) : null;

            // The wine is gone from the catalogue entirely.
            if ($product === null) {
                $changes[] = ['wine_name' => $line->wine_name, 'change' => 'no longer in your catalogue'];
                $items[] = $this->carriedOver($line);

                continue;
            }

            if ($product->archived_at !== null) {
                $changes[] = ['wine_name' => $line->wine_name, 'change' => 'no longer listed by this supplier'];
                $items[] = $this->carriedOver($line);

                continue;
            }

            $wasPoa = $line->unit_price_at_order === null;
            $isPoa = ! $product->price_state->expectsPrice();

            if ($isPoa && ! $wasPoa) {
                $changes[] = ['wine_name' => $line->wine_name, 'change' => 'now price on application'];
            } elseif (! $isPoa && $wasPoa) {
                $changes[] = ['wine_name' => $line->wine_name, 'change' => 'now priced'];
            } elseif (! $isPoa && ! $this->samePrice($line->unit_price_at_order, $product->unit_price)) {
                $changes[] = [
                    'wine_name' => $line->wine_name,
                    'change' => sprintf(
                        'price changed from %s to %s a bottle',
                        number_format((float) $line->unit_price_at_order, 2),
                        number_format((float) $product->unit_price, 2),
                    ),
                ];
            }

            $items[] = new OrderItemData(
                id: null,
                order_id: null,
                product_id: $product->id,
                wine_name: $product->wine_name,
                quantity_units: $line->quantity_units,
                unit_price_at_order: $isPoa ? null : number_format((float) ($product->unit_price ?? 0), 2, '.', ''),
                currency_at_order: $line->currency_at_order,
                sold_by_at_order: $product->sold_by->value,
                pack_size_at_order: $product->soldByCase() ? $product->case_size : null,
                pack_price_at_order: $product->soldByCase() ? $product->displayPrice() : null,
            );
        }

        $order = (new CreateOrderAction)->execute(new OrderData(
            id: null,
            uuid: null,
            company_id: $source->company_id,
            supplier_id: $source->supplier_id,
            venue_id: $source->venue_id,
            created_by: $createdBy ?? $source->created_by,
            status: OrderStatus::Draft,
            total: null,
            notes: 'Repeat of '.$source->displayNumber().'.',
            items: $items,
        ));

        return ['order' => $order, 'changes' => $changes];
    }

    /**
     * A line whose wine we can no longer price, kept as it was so the buyer
     * can see what they ordered last time.
     */
    private function carriedOver(OrderItemData $line): OrderItemData
    {
        return new OrderItemData(
            id: null,
            order_id: null,
            product_id: $line->product_id,
            wine_name: $line->wine_name,
            quantity_units: $line->quantity_units,
            unit_price_at_order: $line->unit_price_at_order,
            currency_at_order: $line->currency_at_order,
            sold_by_at_order: $line->sold_by_at_order,
            pack_size_at_order: $line->pack_size_at_order,
            pack_price_at_order: $line->pack_price_at_order,
        );
    }

    private function samePrice(?string $was, ?string $now): bool
    {
        return abs((float) $was - (float) $now) < 0.005;
    }
}
