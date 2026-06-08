<?php

declare(strict_types=1);

namespace App\Livewire\Suppliers;

use App\Livewire\Concerns\WithTenant;
use Domain\Supplier\Actions\ConnectCompanyToSupplierAction;
use Domain\Supplier\Actions\CreateSupplierAction;
use Domain\Supplier\Actions\DeleteSupplierAction;
use Domain\Supplier\Actions\DisconnectCompanyFromSupplierAction;
use Domain\Supplier\Actions\SyncSupplierVenuesAction;
use Domain\Supplier\Actions\ToggleSupplierStatusAction;
use Domain\Supplier\Actions\UpdateSupplierAction;
use Domain\Supplier\Data\SupplierData;
use Domain\Supplier\Enums\SupplierStatus;
use Domain\Supplier\Repositories\SupplierRepository;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Suppliers')]
class Index extends Component
{
    use WithTenant;

    public string $tab = 'mine'; // mine | discover

    public string $search = '';

    public string $discoverSearch = '';

    // Add / edit a private supplier.
    public bool $showForm = false;

    public ?int $editingId = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:255')]
    public string $contact = '';

    #[Validate('nullable|email|max:255')]
    public string $email = '';

    #[Validate('nullable|string|max:50')]
    public string $phone = '';

    #[Validate('nullable|string|max:255')]
    public string $location = '';

    public string $status = '';

    // Venue allocation.
    public bool $showAllocate = false;

    public ?int $allocatingId = null;

    /** @var array<int, int> */
    public array $allocVenueIds = [];

    public function create(): void
    {
        $this->reset(['editingId', 'name', 'contact', 'email', 'phone', 'location']);
        $this->status = SupplierStatus::Active->value;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $supplier = $this->guardOwned($id);

        $this->editingId = $supplier->id;
        $this->name = $supplier->name;
        $this->contact = $supplier->contact ?? '';
        $this->email = $supplier->email ?? '';
        $this->phone = $supplier->phone ?? '';
        $this->location = $supplier->location ?? '';
        $this->status = $supplier->status->value;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $companyId = $this->requireCompany();
        $this->validate();

        if ($this->editingId !== null) {
            $this->guardOwned($this->editingId);

            (new UpdateSupplierAction)->execute(new SupplierData(
                id: $this->editingId,
                uuid: null,
                name: $this->name,
                contact: $this->contact ?: null,
                email: $this->email ?: null,
                phone: $this->phone ?: null,
                location: $this->location ?: null,
                status: SupplierStatus::from($this->status ?: SupplierStatus::Active->value),
            ));
            $this->dispatch('toast', message: 'Supplier updated.');
        } else {
            // A buyer-created supplier is private to this company and auto-connected.
            $supplier = (new CreateSupplierAction)->execute(new SupplierData(
                id: null,
                uuid: null,
                name: $this->name,
                contact: $this->contact ?: null,
                email: $this->email ?: null,
                phone: $this->phone ?: null,
                location: $this->location ?: null,
                status: SupplierStatus::Active,
                created_by_company_id: $companyId,
            ));
            (new ConnectCompanyToSupplierAction)->execute($companyId, $supplier->id);
            $this->dispatch('toast', message: 'Supplier added.');
        }

        $this->showForm = false;
        $this->reset(['editingId', 'name', 'contact', 'email', 'phone', 'location']);
    }

    public function delete(int $id): void
    {
        $this->guardOwned($id);

        (new DeleteSupplierAction)->execute($id);
        $this->dispatch('toast', message: 'Supplier deleted.');
    }

    public function toggleStatus(int $id): void
    {
        $this->guardOwned($id);

        (new ToggleSupplierStatusAction)->execute($id);
    }

    public function connect(int $id): void
    {
        $companyId = $this->requireCompany();

        // Only public suppliers can be connected to.
        $supplier = (new SupplierRepository)->find($id);
        abort_if($supplier === null || $supplier->tier->isPublic() === false, 403);

        (new ConnectCompanyToSupplierAction)->execute($companyId, $id);
        $this->dispatch('toast', message: 'Added to your suppliers.');
    }

    public function disconnect(int $id): void
    {
        $companyId = $this->requireCompany();
        abort_unless((new SupplierRepository)->isConnectedToCompany($id, $companyId), 403);

        (new DisconnectCompanyFromSupplierAction)->execute($companyId, $id, $this->companyVenueIds());
        $this->dispatch('toast', message: 'Removed from your suppliers.');
    }

    public function startAllocate(int $id): void
    {
        $companyId = $this->requireCompany();
        abort_unless((new SupplierRepository)->isConnectedToCompany($id, $companyId), 403);

        $this->allocatingId = $id;
        // Only the company's own venues, pre-checked where already allocated.
        $allowed = $this->companyVenueIds();
        $this->allocVenueIds = array_values(array_intersect(
            (new SupplierRepository)->venueIdsForSupplier($id),
            $allowed,
        ));
        $this->showAllocate = true;
    }

    public function saveAllocation(): void
    {
        $companyId = $this->requireCompany();
        abort_if($this->allocatingId === null, 422);
        abort_unless((new SupplierRepository)->isConnectedToCompany($this->allocatingId, $companyId), 403);

        (new SyncSupplierVenuesAction)->execute(
            $this->allocatingId,
            $this->companyVenueIds(),
            $this->allocVenueIds,
        );

        $this->showAllocate = false;
        $this->allocatingId = null;
        $this->dispatch('toast', message: 'Venues updated.');
    }

    private function requireCompany(): int
    {
        $companyId = $this->currentUser()?->company_id;
        abort_if($companyId === null, 403);

        return $companyId;
    }

    /**
     * Guard that the supplier is this company's own (private) record — only those
     * may be edited/deleted/toggled by a buyer.
     */
    private function guardOwned(int $id): SupplierData
    {
        $companyId = $this->requireCompany();
        $supplier = (new SupplierRepository)->find($id);
        abort_unless($supplier !== null && $supplier->created_by_company_id === $companyId, 403);

        return $supplier;
    }

    /**
     * The venues the current user may allocate to — role-aware (owners/managers
     * see all company venues, members only their assigned ones), matching orders.
     *
     * @return array<int, int>
     */
    private function companyVenueIds(): array
    {
        return $this->accessibleVenueIds();
    }

    public function render()
    {
        $companyId = $this->currentUser()?->company_id ?? 0;
        $repo = new SupplierRepository;

        $mine = $repo->connectedToCompany($companyId, $this->search);
        $venues = $this->accessibleVenues();

        // Per-supplier allocated venue names (within the venues the user can see).
        $venueIds = $venues->pluck('id')->all();
        $allocations = $mine->mapWithKeys(fn ($s) => [
            $s->id => array_values(array_intersect($repo->venueIdsForSupplier($s->id), $venueIds)),
        ])->all();

        return view('livewire.suppliers.index', [
            'mine' => $mine,
            'discover' => $this->tab === 'discover' ? $repo->discoverableForCompany($companyId, $this->discoverSearch) : collect(),
            'venues' => $venues,
            'allocations' => $allocations,
            'currentCompanyId' => $companyId,
            'statuses' => SupplierStatus::options(),
        ]);
    }
}
