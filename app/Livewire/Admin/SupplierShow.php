<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use Domain\Admin\Repositories\AdminRepository;
use Domain\Supplier\Actions\AddSupplierNoteAction;
use Domain\Supplier\Actions\ApproveAllForDocumentAction;
use Domain\Supplier\Actions\CreateSupplierUserAction;
use Domain\Supplier\Actions\DeleteSupplierDocumentAction;
use Domain\Supplier\Actions\DeleteSupplierNoteAction;
use Domain\Supplier\Actions\DeleteSupplierUserAction;
use Domain\Supplier\Actions\MakeSupplierPublicAction;
use Domain\Supplier\Actions\MarkSupplierOnboardedAction;
use Domain\Supplier\Actions\RecordCatalogueCommitAction;
use Domain\Supplier\Actions\StoreSupplierDocumentAction;
use Domain\Supplier\Actions\UpdateSupplierAction;
use Domain\Supplier\Data\SupplierData;
use Domain\Supplier\Enums\ParseMode;
use Domain\Supplier\Enums\SupplierStatus;
use Domain\Supplier\Jobs\AnalyseSupplierDocumentJob;
use Domain\Supplier\Repositories\LlmCallRepository;
use Domain\Supplier\Repositories\ParsedWineRepository;
use Domain\Supplier\Repositories\SupplierDocumentRepository;
use Domain\Supplier\Repositories\SupplierNoteRepository;
use Domain\Supplier\Repositories\SupplierParseProfileRepository;
use Domain\Supplier\Repositories\SupplierRepository;
use Domain\Supplier\Repositories\SupplierUserRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.admin')]
#[Title('Supplier')]
class SupplierShow extends Component
{
    use WithFileUploads;

    public string $uuid = '';

    public ?int $supplierId = null;

    // Profile fields.
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

    #[Validate('nullable|string|max:255')]
    public string $address = '';

    #[Validate('nullable|string|max:255')]
    public string $city = '';

    #[Validate('nullable|string|max:50')]
    public string $postcode = '';

    #[Validate('nullable|string|max:255')]
    public string $country = '';

    public string $status = SupplierStatus::Active->value;

    // New-user form.
    #[Validate('required|string|max:255')]
    public string $newUserName = '';

    #[Validate('required|email|max:255')]
    public string $newUserEmail = '';

    public function mount(string $uuid): void
    {
        $supplier = (new SupplierRepository)->findByUuid($uuid);
        abort_if($supplier === null, 404);

        $this->uuid = $supplier->uuid;
        $this->supplierId = $supplier->id;
        $this->fillForm($supplier);
    }

    private function fillForm(SupplierData $supplier): void
    {
        $this->name = $supplier->name;
        $this->contact = $supplier->contact ?? '';
        $this->email = $supplier->email ?? '';
        $this->phone = $supplier->phone ?? '';
        $this->website = $supplier->website ?? '';
        $this->location = $supplier->location ?? '';
        $this->address = $supplier->address ?? '';
        $this->city = $supplier->city ?? '';
        $this->postcode = $supplier->postcode ?? '';
        $this->country = $supplier->country ?? '';
        $this->status = $supplier->status->value;
    }

    public function saveProfile(): void
    {
        $this->ensureAdmin();
        $this->validate([
            'name' => 'required|string|max:255',
            'contact' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'website' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'postcode' => 'nullable|string|max:50',
            'country' => 'nullable|string|max:255',
            'status' => ['required', Rule::enum(SupplierStatus::class)],
        ]);

        $data = new SupplierData(
            id: $this->supplierId,
            uuid: $this->uuid,
            name: $this->name,
            contact: $this->contact ?: null,
            email: $this->email ?: null,
            phone: $this->phone ?: null,
            location: $this->location ?: null,
            status: SupplierStatus::from($this->status),
            address: $this->address ?: null,
            city: $this->city ?: null,
            postcode: $this->postcode ?: null,
            country: $this->country ?: null,
            website: $this->website ?: null,
        );

        (new UpdateSupplierAction)->execute($data);
        $this->dispatch('toast', message: 'Supplier profile saved.');
    }

    public function addUser(): void
    {
        $this->ensureAdmin();
        $this->validate([
            'newUserName' => 'required|string|max:255',
            'newUserEmail' => 'required|email|max:255|unique:supplier_users,email',
        ]);

        (new CreateSupplierUserAction)->execute($this->supplierId, $this->newUserName, $this->newUserEmail);

        // Email an invite so the supplier sets their own password.
        $status = Password::broker('supplier_users')->sendResetLink(['email' => $this->newUserEmail]);

        $this->reset(['newUserName', 'newUserEmail']);
        $this->dispatch('toast', message: $status === Password::RESET_LINK_SENT
            ? 'User added and invite sent.'
            : 'User added, but the invite could not be sent right now.');
    }

    public function resendInvite(int $userId): void
    {
        $this->ensureAdmin();

        $user = (new SupplierUserRepository)->find($userId);
        abort_unless($user !== null && $user->supplier_id === $this->supplierId, 403);

        $status = Password::broker('supplier_users')->sendResetLink(['email' => $user->email]);
        $this->dispatch('toast', message: $status === Password::RESET_LINK_SENT
            ? 'Invite re-sent.'
            : 'Please wait a moment before resending the invite.');
    }

    public function deleteUser(int $userId): void
    {
        $this->ensureAdmin();

        $user = (new SupplierUserRepository)->find($userId);
        abort_unless($user !== null && $user->supplier_id === $this->supplierId, 403);

        (new DeleteSupplierUserAction)->execute($userId);
        $this->dispatch('toast', message: 'User removed.');
    }

    // CRM note (admin-only relationship log).
    #[Validate('required|string|max:2000')]
    public string $newNote = '';

    public function addNote(): void
    {
        $this->ensureAdmin();
        $this->validate(['newNote' => 'required|string|max:2000']);

        (new AddSupplierNoteAction)->execute(
            $this->supplierId,
            trim($this->newNote),
            (new AdminRepository)->getLoggedInAdmin()?->id,
        );

        $this->newNote = '';
        $this->dispatch('toast', message: 'Note added.');
    }

    public function deleteNote(int $noteId): void
    {
        $this->ensureAdmin();

        $note = (new SupplierNoteRepository)->find($noteId);
        abort_unless($note !== null && $note->supplier_id === $this->supplierId, 403);

        (new DeleteSupplierNoteAction)->execute($noteId);
        $this->dispatch('toast', message: 'Note removed.');
    }

    public function makePublic(): void
    {
        $this->ensureAdmin();
        (new MakeSupplierPublicAction)->execute($this->supplierId);
        $this->dispatch('toast', message: 'Supplier is now listed publicly.');
    }

    public function markOnboarded(): void
    {
        $this->ensureAdmin();
        (new MarkSupplierOnboardedAction)->execute($this->supplierId);
        $this->dispatch('toast', message: 'Supplier marked as onboarded.');
    }

    // Document upload (mirrors the portal/buyer flow; admin uploads are global
    // scope — no uploader ids — so their parse profiles benefit every tenant).
    #[Validate('nullable|string|max:255')]
    public string $docTitle = '';

    #[Validate('nullable|url|max:2048')]
    public string $docSourceUrl = '';

    #[Validate('required|file|mimes:csv,txt,xls,xlsx,pdf|max:20480')]
    public $docUpload;

    public function uploadDocument(): void
    {
        $this->ensureAdmin();
        $this->validate([
            'docTitle' => 'nullable|string|max:255',
            'docSourceUrl' => 'nullable|url|max:2048',
            'docUpload' => 'required|file|mimes:csv,txt,xls,xlsx,pdf|max:20480',
        ]);

        $path = $this->docUpload->store('supplier-documents', 'local');

        (new StoreSupplierDocumentAction)->execute(
            supplierId: $this->supplierId,
            uploadedBySupplierUserId: null,
            title: $this->docTitle ?: null,
            fileName: $this->docUpload->getClientOriginalName(),
            fileType: $this->docUpload->getMimeType(),
            fileSize: $this->docUpload->getSize(),
            storagePath: $path,
            sourceUrl: $this->docSourceUrl ?: null,
            // Hash the stored copy so the weekly refresh only re-ingests this
            // source when the published file actually changes.
            contentSha256: $this->docSourceUrl !== '' ? hash('sha256', $this->docUpload->get()) : null,
        );

        $this->reset(['docTitle', 'docSourceUrl', 'docUpload']);
        $this->dispatch('toast', message: 'Document uploaded. Awaiting analysis.');
    }

    public function analyse(int $documentId): void
    {
        $this->ensureAdmin();

        $document = (new SupplierDocumentRepository)->find($documentId);
        abort_unless($document !== null && $document->supplier_id === $this->supplierId, 403);

        AnalyseSupplierDocumentJob::dispatch($documentId, full: true);
        $this->dispatch('toast', message: 'Analysis queued.');
    }

    public function approveDocument(int $documentId): void
    {
        $this->ensureAdmin();

        $document = (new SupplierDocumentRepository)->find($documentId);
        abort_unless($document !== null && $document->supplier_id === $this->supplierId, 403);

        // Bulk approve from the admin list has no row-level review surface, so
        // flagged rows are skipped rather than committed unseen.
        $count = (new ApproveAllForDocumentAction)->execute($documentId, skipFlagged: true);
        (new RecordCatalogueCommitAction)->execute($this->supplierId, $document->file_name, $count);
        $this->dispatch('toast', message: "{$count} unflagged wine(s) added to the catalogue.");
    }

    public function deleteDocument(int $documentId): void
    {
        $this->ensureAdmin();

        $document = (new SupplierDocumentRepository)->find($documentId);
        abort_unless($document !== null && $document->supplier_id === $this->supplierId, 403);

        (new DeleteSupplierDocumentAction)->execute($documentId);
        $this->dispatch('toast', message: 'Document removed.');
    }

    private function ensureAdmin(): void
    {
        abort_unless(Auth::guard('admin')->check(), 403);
    }

    public function render()
    {
        $documents = (new SupplierDocumentRepository)->forSupplier($this->supplierId);
        $parsedRepo = new ParsedWineRepository;
        $profileRepo = new SupplierParseProfileRepository;

        // The learned recipe per mode = "exactly how we parse this supplier".
        $profiles = collect(ParseMode::cases())
            ->mapWithKeys(fn (ParseMode $mode) => [$mode->value => $profileRepo->activeForSupplier($this->supplierId, $mode)])
            ->filter()
            ->all();

        return view('livewire.admin.supplier-show', [
            'supplier' => (new SupplierRepository)->find($this->supplierId),
            'users' => (new SupplierUserRepository)->forSupplier($this->supplierId),
            'documents' => $documents,
            'notes' => (new SupplierNoteRepository)->forSupplier($this->supplierId),
            'parsedCounts' => $documents->mapWithKeys(fn ($d) => [$d->id => $parsedRepo->countsForDocument($d->id)])->all(),
            'parseProfiles' => $profiles,
            'aiSpend' => (new LlmCallRepository)->totalsForSupplier($this->supplierId),
            'statuses' => SupplierStatus::options(),
        ]);
    }
}
