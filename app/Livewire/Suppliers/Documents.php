<?php

declare(strict_types=1);

namespace App\Livewire\Suppliers;

use App\Livewire\Concerns\WithTenant;
use Domain\Supplier\Actions\DeleteSupplierDocumentAction;
use Domain\Supplier\Actions\StoreSupplierDocumentAction;
use Domain\Supplier\Jobs\AnalyseSupplierDocumentJob;
use Domain\Supplier\Repositories\SupplierDocumentRepository;
use Domain\Supplier\Repositories\SupplierRepository;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Supplier documents')]
class Documents extends Component
{
    use WithFileUploads;
    use WithTenant;

    public string $uuid = '';

    public ?int $supplierId = null;

    public string $supplierName = '';

    #[Validate('nullable|string|max:255')]
    public string $docTitle = '';

    #[Validate('required|file|mimes:csv,txt,xls,xlsx,pdf|max:20480')]
    public $upload;

    public function mount(string $uuid): void
    {
        $companyId = $this->requireCompany();
        $supplier = (new SupplierRepository)->findByUuid($uuid);
        abort_if($supplier === null, 404);

        // You can only manage documents for a supplier you're connected to.
        abort_unless((new SupplierRepository)->isConnectedToCompany($supplier->id, $companyId), 403);

        $this->uuid = $supplier->uuid;
        $this->supplierId = $supplier->id;
        $this->supplierName = $supplier->name;
    }

    public function upload(): void
    {
        $user = $this->currentUser();
        $companyId = $this->requireCompany();
        $this->validate();

        // Read metadata BEFORE store(): Livewire 4 MOVES the temp file when
        // the target is the same disk, so metadata reads after store() throw.
        $file_name = $this->upload->getClientOriginalName();
        $file_type = $this->upload->getMimeType();
        $file_size = $this->upload->getSize();

        $path = $this->upload->store('supplier-documents', 'local');

        (new StoreSupplierDocumentAction)->execute(
            supplierId: $this->supplierId,
            uploadedBySupplierUserId: null,
            title: $this->docTitle ?: null,
            fileName: $file_name,
            fileType: $file_type,
            fileSize: $file_size,
            storagePath: $path,
            uploadedByCompanyId: $companyId,
            uploadedByUserId: $user?->id,
        );

        $this->reset(['docTitle', 'upload']);
        $this->dispatch('toast', message: 'Document uploaded. Awaiting analysis.');
    }

    public function analyse(int $id): void
    {
        $this->guardOwnDocument($id);
        AnalyseSupplierDocumentJob::dispatch($id);
        $this->dispatch('toast', message: 'Analysis queued.');
    }

    public function delete(int $id): void
    {
        $this->guardOwnDocument($id);
        (new DeleteSupplierDocumentAction)->execute($id);
        $this->dispatch('toast', message: 'Document removed.');
    }

    private function requireCompany(): int
    {
        $companyId = $this->currentUser()?->company_id;
        abort_if($companyId === null, 403);

        return $companyId;
    }

    private function guardOwnDocument(int $id): void
    {
        $document = (new SupplierDocumentRepository)->find($id);
        abort_unless(
            $document !== null && $document->uploaded_by_company_id === $this->currentUser()?->company_id,
            403
        );
    }

    public function render()
    {
        $companyId = $this->currentUser()?->company_id ?? 0;

        return view('livewire.suppliers.documents', [
            'documents' => (new SupplierDocumentRepository)->forSupplierAndCompany($this->supplierId, $companyId),
        ]);
    }
}
