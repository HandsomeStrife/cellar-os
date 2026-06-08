<?php

declare(strict_types=1);

namespace App\Livewire\Inventory;

use App\Livewire\Concerns\WithTenant;
use Domain\Billing\Enums\Feature;
use Domain\Catalogue\Repositories\ProductRepository;
use Domain\Inventory\Actions\AddInventoryAttachmentAction;
use Domain\Inventory\Actions\AddInventoryItemAction;
use Domain\Inventory\Actions\AdjustInventoryQuantityAction;
use Domain\Inventory\Actions\ArchiveInventoryItemAction;
use Domain\Inventory\Actions\DeleteInventoryAttachmentAction;
use Domain\Inventory\Actions\RestoreInventoryItemAction;
use Domain\Inventory\Repositories\InventoryAttachmentRepository;
use Domain\Inventory\Repositories\InventoryItemRepository;
use Domain\Venue\Actions\CreateVenueAction;
use Domain\Venue\Data\VenueData;
use Domain\Venue\Repositories\VenueRepository;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Session;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Inventory')]
class Index extends Component
{
    use WithFileUploads;
    use WithTenant;

    #[Session]
    public ?int $venueId = null;

    public bool $showArchived = false;

    public string $search = '';

    // New-venue form
    public bool $showVenueForm = false;

    #[Validate('required|string|max:255')]
    public string $venueName = '';

    // Add-item form
    public bool $showAddForm = false;

    public string $productSearch = '';

    public ?int $addProductId = null;

    public int $addQuantity = 1;

    public ?string $addPrice = null;

    // Attachments
    public ?int $attachmentItemId = null;

    public $upload;

    private function can(Feature $feature): bool
    {
        return $this->companyPlan()->can($feature);
    }

    /**
     * Resolve the active venue, ensuring the current user can access it.
     */
    private function activeVenueId(): ?int
    {
        $venues = $this->accessibleVenues();

        if ($venues->isEmpty()) {
            return null;
        }

        // Fall back to the first accessible venue if the stored one isn't (or no longer) reachable.
        if ($this->venueId === null || ! $venues->contains(fn (VenueData $v) => $v->id === $this->venueId)) {
            $this->venueId = $venues->first()->id;
        }

        return $this->venueId;
    }

    public function selectVenue(int $id): void
    {
        abort_unless($this->can(Feature::Inventory), 403);

        if ($this->accessibleVenues()->contains(fn (VenueData $v) => $v->id === $id)) {
            $this->venueId = $id;
        }
    }

    public function createVenue(): void
    {
        abort_unless($this->can(Feature::Inventory), 403);

        // Only owners/managers add venues to the company.
        $company = $this->currentCompany();
        abort_unless($company !== null && ($this->currentUser()?->role->canManageTeam() ?? false), 403);

        // First venue is included; additional venues require the Group plan.
        $existing = (new VenueRepository)->getForCompany($company->id);
        abort_unless($existing->isEmpty() || $this->can(Feature::MultiVenue), 403);

        $this->validate(['venueName' => 'required|string|max:255']);

        $venue = (new CreateVenueAction)->execute(new VenueData(
            id: null,
            uuid: null,
            company_id: $company->id,
            name: $this->venueName,
            address: null,
            city: null,
            country: null,
            base_currency: $company->base_currency,
        ));

        $this->venueId = $venue->id;
        $this->showVenueForm = false;
        $this->venueName = '';
        $this->dispatch('toast', message: 'Venue created.');
    }

    public function saveItem(): void
    {
        abort_unless($this->can(Feature::ManualInventoryAdd), 403);

        $this->validate([
            'addProductId' => 'required|integer|exists:products,id',
            'addQuantity' => 'required|integer|min:1',
            'addPrice' => 'nullable|numeric|min:0',
        ]);

        $venueId = $this->activeVenueId();
        abort_if($venueId === null, 422);

        (new AddInventoryItemAction)->execute(
            venueId: $venueId,
            productId: $this->addProductId,
            quantity: $this->addQuantity,
            price: $this->addPrice !== null && $this->addPrice !== '' ? (float) $this->addPrice : null,
        );

        $this->reset(['showAddForm', 'addProductId', 'addQuantity', 'addPrice', 'productSearch']);
        $this->addQuantity = 1;
        $this->dispatch('toast', message: 'Stock received.');
    }

    public function adjustQuantity(int $id, int $quantity): void
    {
        abort_unless($this->can(Feature::Inventory), 403);
        $this->guardOwnsItem($id);
        (new AdjustInventoryQuantityAction)->execute($id, $quantity);
    }

    public function archive(int $id): void
    {
        abort_unless($this->can(Feature::InventoryArchive), 403);
        $this->guardOwnsItem($id);
        (new ArchiveInventoryItemAction)->execute($id);
        $this->dispatch('toast', message: 'Item archived.');
    }

    public function restore(int $id): void
    {
        abort_unless($this->can(Feature::InventoryArchive), 403);
        $this->guardOwnsItem($id);
        (new RestoreInventoryItemAction)->execute($id);
        $this->dispatch('toast', message: 'Item restored.');
    }

    public function openAttachments(int $id): void
    {
        abort_unless($this->can(Feature::Inventory), 403);
        $this->guardOwnsItem($id);
        $this->attachmentItemId = $id;
    }

    public function uploadAttachment(): void
    {
        abort_unless($this->can(Feature::InventoryAttachments), 403);
        abort_if($this->attachmentItemId === null, 422);
        $this->guardOwnsItem($this->attachmentItemId);

        $this->validate([
            'upload' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,csv,xls,xlsx',
        ]);

        $path = $this->upload->store('inventory-attachments', 'local');

        (new AddInventoryAttachmentAction)->execute(
            inventoryItemId: $this->attachmentItemId,
            uploadedBy: $this->currentUser()?->id,
            fileName: $this->upload->getClientOriginalName(),
            fileType: $this->upload->getMimeType(),
            fileSize: $this->upload->getSize(),
            storagePath: $path,
        );

        $this->reset('upload');
        $this->dispatch('toast', message: 'Attachment uploaded.');
    }

    public function deleteAttachment(int $id): void
    {
        abort_unless($this->can(Feature::InventoryAttachments), 403);

        $attachment = (new InventoryAttachmentRepository)->find($id);
        abort_if($attachment === null, 404);
        $this->guardOwnsItem($attachment->inventory_item_id);

        $path = (new DeleteInventoryAttachmentAction)->execute($id);
        Storage::disk('local')->delete($path);

        $this->dispatch('toast', message: 'Attachment removed.');
    }

    /**
     * Ensure the inventory item belongs to one of the current user's venues.
     */
    private function guardOwnsItem(int $id): void
    {
        $item = (new InventoryItemRepository)->find($id);
        $owns = $item !== null
            && $this->accessibleVenues()->contains(fn (VenueData $v) => $v->id === $item->venue_id);

        abort_unless($owns, 403);
    }

    public function render()
    {
        $canInventory = $this->can(Feature::Inventory);

        $venues = $this->accessibleVenues();
        $venueId = $this->activeVenueId();

        $rows = collect();
        $productOptions = [];

        if ($canInventory && $venueId !== null) {
            $inventoryRepo = new InventoryItemRepository;
            $productRepo = new ProductRepository;

            $items = $this->showArchived
                ? $inventoryRepo->archived($venueId)
                : $inventoryRepo->forVenue($venueId);

            $rows = $items
                ->map(function ($item) use ($productRepo) {
                    $product = $item->product_id ? $productRepo->find($item->product_id) : null;

                    return ['item' => $item, 'product' => $product];
                })
                ->filter(function ($row) {
                    if ($this->search === '') {
                        return true;
                    }

                    $name = $row['product']?->wine_name ?? '';

                    return stripos($name, $this->search) !== false;
                })
                ->values();

            if ($this->showAddForm) {
                $productOptions = $productRepo->search(term: $this->productSearch, perPage: 25)
                    ->getCollection()
                    ->mapWithKeys(fn ($p) => [$p->id => $p->wine_name.($p->vintage ? " ({$p->vintage})" : '')])
                    ->all();
            }
        }

        return view('livewire.inventory.index', [
            'canInventory' => $canInventory,
            'canManualAdd' => $this->can(Feature::ManualInventoryAdd),
            'canArchive' => $this->can(Feature::InventoryArchive),
            'canAttachments' => $this->can(Feature::InventoryAttachments),
            'canMultiVenue' => $this->can(Feature::MultiVenue),
            'plan' => $this->companyPlan(),
            'venues' => $venues,
            'venueId' => $venueId,
            'rows' => $rows,
            'productOptions' => $productOptions,
        ]);
    }
}
