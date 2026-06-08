<?php

declare(strict_types=1);

namespace App\Livewire\SupplierPortal;

use Domain\Supplier\Repositories\SupplierRepository;
use Domain\Supplier\Repositories\SupplierUserRepository;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.supplier')]
#[Title('Company profile')]
class Profile extends Component
{
    public function render()
    {
        $supplierUser = (new SupplierUserRepository)->getLoggedInSupplierUser();
        $supplier = $supplierUser ? (new SupplierRepository)->find($supplierUser->supplier_id) : null;
        $colleagues = $supplierUser
            ? (new SupplierUserRepository)->forSupplier($supplierUser->supplier_id)
            : collect();

        return view('livewire.supplier-portal.profile', [
            'supplierUser' => $supplierUser,
            'supplier' => $supplier,
            'colleagues' => $colleagues,
        ]);
    }
}
