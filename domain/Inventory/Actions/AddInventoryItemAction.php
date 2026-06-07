<?php

declare(strict_types=1);

namespace Domain\Inventory\Actions;

use Domain\Inventory\Data\InventoryItemData;
use Domain\Inventory\Models\InventoryItem;
use Domain\Shared\Actions\AbstractAction;

class AddInventoryItemAction extends AbstractAction
{
    /**
     * Receive stock for a product into a venue. Inventory is unique per
     * (venue, product): re-receiving an existing line tops up its quantity
     * and un-archives it.
     */
    public function execute(
        int $venueId,
        int $productId,
        int $quantity,
        ?float $price = null,
        string $currency = 'GBP',
    ): InventoryItemData {
        $item = InventoryItem::firstOrNew([
            'venue_id' => $venueId,
            'product_id' => $productId,
        ]);

        $item->quantity_units = ($item->exists ? $item->quantity_units : 0) + max(0, $quantity);

        if ($price !== null) {
            $item->last_purchase_price = $price;
            $item->last_purchase_currency = $currency;
        }

        $item->last_received_at = now();
        $item->is_archived = false;
        $item->archived_at = null;
        $item->save();

        return $item->getData();
    }
}
