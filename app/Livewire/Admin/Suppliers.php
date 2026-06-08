<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use Domain\Supplier\Actions\CreateSupplierAction;
use Domain\Supplier\Data\SupplierData;
use Domain\Supplier\Enums\SupplierStatus;
use Domain\Supplier\Repositories\SupplierRepository;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Suppliers')]
class Suppliers extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showForm = false;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:255')]
    public string $contact = '';

    #[Validate('nullable|email|max:255')]
    public string $email = '';

    #[Validate('nullable|string|max:50')]
    public string $phone = '';

    #[Validate('nullable|string|max:255')]
    public string $website = '';

    #[Validate('nullable|string|max:255')]
    public string $location = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->reset(['name', 'contact', 'email', 'phone', 'website', 'location']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->ensureAdmin();
        $this->validate();

        $data = new SupplierData(
            id: null,
            uuid: null,
            name: $this->name,
            contact: $this->contact ?: null,
            email: $this->email ?: null,
            phone: $this->phone ?: null,
            location: $this->location ?: null,
            status: SupplierStatus::Active,
            website: $this->website ?: null,
        );

        $supplier = (new CreateSupplierAction)->execute($data);

        $this->showForm = false;
        $this->reset(['name', 'contact', 'email', 'phone', 'website', 'location']);

        $this->redirectRoute('admin.suppliers.show', ['uuid' => $supplier->uuid], navigate: true);
    }

    private function ensureAdmin(): void
    {
        abort_unless(Auth::guard('admin')->check(), 403);
    }

    public function render()
    {
        return view('livewire.admin.suppliers', [
            'suppliers' => (new SupplierRepository)->paginate($this->search),
        ]);
    }
}
