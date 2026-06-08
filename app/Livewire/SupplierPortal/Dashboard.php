<?php

declare(strict_types=1);

namespace App\Livewire\SupplierPortal;

use Domain\Supplier\Enums\SupplierDocumentStatus;
use Domain\Supplier\Repositories\SupplierDocumentRepository;
use Domain\Supplier\Repositories\SupplierRepository;
use Domain\Supplier\Repositories\SupplierUserRepository;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.supplier')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render()
    {
        $supplierUser = (new SupplierUserRepository)->getLoggedInSupplierUser();
        $supplier = $supplierUser ? (new SupplierRepository)->find($supplierUser->supplier_id) : null;
        $documents = $supplierUser
            ? (new SupplierDocumentRepository)->forSupplierPortal($supplierUser->supplier_id)
            : collect();

        return view('livewire.supplier-portal.dashboard', [
            'supplierUser' => $supplierUser,
            'supplier' => $supplier,
            'documents' => $documents,
            'awaitingCount' => $documents->where('status', SupplierDocumentStatus::AwaitingAnalysis)->count(),
            'analysedCount' => $documents->where('status', SupplierDocumentStatus::Analysed)->count(),
        ]);
    }
}
