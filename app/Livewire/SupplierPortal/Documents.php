<?php

declare(strict_types=1);

namespace App\Livewire\SupplierPortal;

use Domain\Supplier\Actions\DeleteSupplierDocumentAction;
use Domain\Supplier\Actions\StoreSupplierDocumentAction;
use Domain\Supplier\Repositories\SupplierDocumentRepository;
use Domain\Supplier\Repositories\SupplierUserRepository;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.supplier')]
#[Title('Documents')]
class Documents extends Component
{
    use WithFileUploads;

    #[Validate('nullable|string|max:255')]
    public string $title = '';

    #[Validate('required|file|mimes:csv,txt,xls,xlsx,pdf|max:20480')]
    public $upload;

    public function upload(): void
    {
        $this->validate();

        $supplierUser = (new SupplierUserRepository)->getLoggedInSupplierUser();
        abort_if($supplierUser === null, 403);

        // Read metadata BEFORE store(): Livewire 4 MOVES the temp file when
        // the target is the same disk, so metadata reads after store() throw.
        $file_name = $this->upload->getClientOriginalName();
        $file_type = $this->upload->getMimeType();
        $file_size = $this->upload->getSize();

        $path = $this->upload->store('supplier-documents', 'local');

        (new StoreSupplierDocumentAction)->execute(
            supplierId: $supplierUser->supplier_id,
            uploadedBySupplierUserId: $supplierUser->id,
            title: $this->title ?: null,
            fileName: $file_name,
            fileType: $file_type,
            fileSize: $file_size,
            storagePath: $path,
        );

        $this->reset(['title', 'upload']);
        $this->dispatch('toast', message: 'Document uploaded. Awaiting analysis.');
    }

    public function delete(int $id): void
    {
        $supplierUser = (new SupplierUserRepository)->getLoggedInSupplierUser();
        $document = (new SupplierDocumentRepository)->find($id);

        // Only the owning supplier may remove its OWN documents — never a
        // buyer's private upload about them (uploaded_by_company_id set).
        abort_unless(
            $supplierUser !== null
                && $document !== null
                && $document->supplier_id === $supplierUser->supplier_id
                && $document->uploaded_by_company_id === null,
            403
        );

        (new DeleteSupplierDocumentAction)->execute($id);
        $this->dispatch('toast', message: 'Document removed.');
    }

    public function render()
    {
        $supplierUser = (new SupplierUserRepository)->getLoggedInSupplierUser();
        $documents = $supplierUser
            ? (new SupplierDocumentRepository)->forSupplierPortal($supplierUser->supplier_id)
            : collect();

        return view('livewire.supplier-portal.documents', [
            'documents' => $documents,
        ]);
    }
}
