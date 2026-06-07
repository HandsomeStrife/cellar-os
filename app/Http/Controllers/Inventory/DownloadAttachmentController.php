<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use Domain\Inventory\Repositories\InventoryAttachmentRepository;
use Domain\Inventory\Repositories\InventoryItemRepository;
use Domain\User\Repositories\UserRepository;
use Domain\Venue\Data\VenueData;
use Domain\Venue\Repositories\VenueRepository;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadAttachmentController
{
    public function __invoke(int $id): StreamedResponse
    {
        $attachment = (new InventoryAttachmentRepository)->find($id);
        abort_if($attachment === null, 404);

        $item = (new InventoryItemRepository)->find($attachment->inventory_item_id);
        abort_if($item === null, 404);

        // Authorize: the item's venue must belong to the current user.
        $userId = (new UserRepository)->getLoggedInUser()?->id;
        $owns = (new VenueRepository)->getForUser($userId ?? 0)
            ->contains(fn (VenueData $venue) => $venue->id === $item->venue_id);
        abort_unless($owns, 403);

        abort_unless(Storage::disk('local')->exists($attachment->storage_path), 404);

        return Storage::disk('local')->download($attachment->storage_path, $attachment->file_name);
    }
}
