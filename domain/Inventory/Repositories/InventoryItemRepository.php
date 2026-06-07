<?php

declare(strict_types=1);

namespace Domain\Inventory\Repositories;

use Domain\Inventory\Data\InventoryItemData;
use Domain\Inventory\Models\InventoryItem;
use Illuminate\Support\Collection;

class InventoryItemRepository
{
    public function find(int $id): ?InventoryItemData
    {
        return InventoryItem::with('attachments')->find($id)?->getData();
    }

    public function findByUuid(string $uuid): ?InventoryItemData
    {
        return InventoryItem::with('attachments')->where('uuid', $uuid)->first()?->getData();
    }

    public function forVenue(int $venueId): Collection
    {
        return InventoryItem::active()
            ->where('venue_id', $venueId)
            ->get()
            ->map(fn (InventoryItem $item) => $item->getData());
    }

    public function archived(int $venueId): Collection
    {
        return InventoryItem::where('is_archived', true)
            ->where('venue_id', $venueId)
            ->get()
            ->map(fn (InventoryItem $item) => $item->getData());
    }
}
