<?php

declare(strict_types=1);

namespace App\Livewire\Suppliers;

use Domain\Supplier\Actions\CreateSupplierAction;
use Domain\Supplier\Actions\DeleteSupplierAction;
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
    public string $search = '';

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

    #[Validate('required')]
    public string $status = SupplierStatus::Active->value;

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $supplier = (new SupplierRepository)->find($id);

        if ($supplier === null) {
            return;
        }

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
        $this->validate();

        $data = new SupplierData(
            id: $this->editingId,
            uuid: null,
            name: $this->name,
            contact: $this->contact ?: null,
            email: $this->email ?: null,
            phone: $this->phone ?: null,
            location: $this->location ?: null,
            status: SupplierStatus::from($this->status),
        );

        if ($this->editingId !== null) {
            (new UpdateSupplierAction)->execute($data);
            $this->dispatch('toast', message: 'Supplier updated.');
        } else {
            (new CreateSupplierAction)->execute($data);
            $this->dispatch('toast', message: 'Supplier created.');
        }

        $this->showForm = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        (new DeleteSupplierAction)->execute($id);
        $this->dispatch('toast', message: 'Supplier deleted.');
    }

    public function toggleStatus(int $id): void
    {
        (new ToggleSupplierStatusAction)->execute($id);
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'contact', 'email', 'phone', 'location']);
        $this->status = SupplierStatus::Active->value;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.suppliers.index', [
            'suppliers' => (new SupplierRepository)->search($this->search),
            'statuses' => SupplierStatus::options(),
        ]);
    }
}
