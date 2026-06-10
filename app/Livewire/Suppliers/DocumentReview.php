<?php

declare(strict_types=1);

namespace App\Livewire\Suppliers;

use App\Livewire\Concerns\WithTenant;
use Domain\Catalogue\Enums\WineColour;
use Domain\Supplier\Actions\ApproveAllForDocumentAction;
use Domain\Supplier\Actions\ApproveParsedWineAction;
use Domain\Supplier\Actions\RefineParseProfileAction;
use Domain\Supplier\Actions\RejectParsedWineAction;
use Domain\Supplier\Actions\UpdateParsedWineAction;
use Domain\Supplier\Data\ParsedWineData;
use Domain\Supplier\Enums\ParseMode;
use Domain\Supplier\Jobs\AnalyseSupplierDocumentJob;
use Domain\Supplier\Repositories\ParsedWineRepository;
use Domain\Supplier\Repositories\SupplierDocumentRepository;
use Domain\Supplier\Repositories\SupplierParseProfileRepository;
use Domain\Supplier\Repositories\SupplierRepository;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Review parsed wines')]
class DocumentReview extends Component
{
    use WithPagination;
    use WithTenant;

    /** Models a reviewer may pick for re-runs. */
    public const MODELS = ['claude-opus-4-8', 'claude-sonnet-4-6'];

    #[Locked]
    public string $uuid = '';

    #[Locked]
    public int $documentId = 0;

    #[Locked]
    public string $supplierName = '';

    #[Locked]
    public int $supplierId = 0;

    public string $model = '';

    // Inline edit state.
    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $edit = [];

    public function mount(string $uuid, int $documentId): void
    {
        $companyId = $this->requireCompany();
        $supplier = (new SupplierRepository)->findByUuid($uuid);
        abort_if($supplier === null, 404);

        $document = (new SupplierDocumentRepository)->find($documentId);
        abort_unless(
            $document !== null
                && $document->supplier_id === $supplier->id
                && $document->uploaded_by_company_id === $companyId,
            403
        );

        $this->uuid = $supplier->uuid;
        $this->supplierId = $supplier->id;
        $this->supplierName = $supplier->name;
        $this->documentId = $documentId;
        $this->model = (string) config('services.anthropic.model', 'claude-opus-4-8');
    }

    public function runFull(): void
    {
        $this->guardDocument();
        AnalyseSupplierDocumentJob::dispatch($this->documentId, full: true, model: $this->chosenModel());
        $this->dispatch('toast', message: 'Full extraction queued.');
    }

    public function reanalyse(): void
    {
        $this->guardDocument();
        AnalyseSupplierDocumentJob::dispatch($this->documentId, full: false, model: $this->chosenModel());
        $this->dispatch('toast', message: 'Re-analysis queued.');
    }

    public function approve(int $id): void
    {
        $this->guardRow($id);
        $this->guardCanCommit();
        (new ApproveParsedWineAction)->execute($id);
        $this->dispatch('toast', message: 'Wine added to your catalogue.');
    }

    public function reject(int $id): void
    {
        $this->guardRow($id);
        (new RejectParsedWineAction)->execute($id);
    }

    public function approveAll(): void
    {
        $this->guardDocument();
        $this->guardCanCommit();
        $count = (new ApproveAllForDocumentAction)->execute($this->documentId);
        $this->dispatch('toast', message: "{$count} wine(s) added to your catalogue.");
    }

    public function saveRecipe(): void
    {
        $this->guardDocument();
        $document = (new SupplierDocumentRepository)->find($this->documentId);
        $examples = (new ParsedWineRepository)->approvedExamples($this->documentId);
        (new RefineParseProfileAction)->execute(
            $document->supplier_id,
            ParseMode::forFileType($document->file_type, $document->file_name),
            $examples,
            $this->currentUser()?->company_id,
        );
        $this->dispatch('toast', message: 'Saved these corrections to the supplier recipe.');
    }

    public function startEdit(int $id): void
    {
        $row = $this->guardRow($id);
        $p = $row->payload;

        $this->editingId = $id;
        $this->edit = [
            'wine_name' => $p['wine_name'] ?? '',
            'producer' => $p['producer'] ?? '',
            'vintage' => $p['vintage'] ?? '',
            'unit_price' => $p['unit_price'] ?? '',
            'colour' => $p['colour'] ?? '',
            'country' => $p['country'] ?? '',
            'region' => $p['region'] ?? '',
            'grape' => is_array($p['grape'] ?? null) ? implode(', ', $p['grape']) : '',
        ];
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->edit = [];
    }

    public function saveEdit(): void
    {
        abort_if($this->editingId === null, 422);
        $this->guardRow($this->editingId);

        $this->validate([
            'edit.wine_name' => 'required|string|max:255',
            'edit.producer' => 'nullable|string|max:255',
            'edit.vintage' => 'nullable|integer|between:1900,'.(now()->year + 1),
            'edit.unit_price' => 'nullable|numeric|between:0,100000',
            'edit.colour' => ['nullable', Rule::in(array_column(WineColour::cases(), 'value'))],
            'edit.country' => 'nullable|string|max:255',
            'edit.region' => 'nullable|string|max:255',
            'edit.grape' => 'nullable|string|max:500',
        ]);

        $changes = [
            'wine_name' => trim((string) $this->edit['wine_name']) ?: null,
            'producer' => trim((string) $this->edit['producer']) ?: null,
            'vintage' => $this->edit['vintage'] !== '' ? (int) $this->edit['vintage'] : null,
            'unit_price' => $this->edit['unit_price'] !== '' ? number_format((float) $this->edit['unit_price'], 2, '.', '') : null,
            'colour' => $this->edit['colour'] !== '' ? $this->edit['colour'] : null,
            'country' => trim((string) $this->edit['country']) ?: null,
            'region' => trim((string) $this->edit['region']) ?: null,
            'grape' => trim((string) $this->edit['grape']) !== ''
                ? array_values(array_filter(array_map('trim', explode(',', (string) $this->edit['grape']))))
                : null,
        ];

        (new UpdateParsedWineAction)->execute($this->editingId, $changes);
        $this->cancelEdit();
        $this->dispatch('toast', message: 'Wine updated.');
    }

    private function requireCompany(): int
    {
        $companyId = $this->currentUser()?->company_id;
        abort_if($companyId === null, 403);

        return $companyId;
    }

    private function guardDocument(): void
    {
        $document = (new SupplierDocumentRepository)->find($this->documentId);
        abort_unless(
            $document !== null && $document->uploaded_by_company_id === $this->currentUser()?->company_id,
            403
        );
    }

    /**
     * Committing wines to the catalogue is only allowed for the company's OWN
     * private suppliers — shared (Listed/Onboarded) catalogues are read-only
     * for buyers; their parses are review-only until published centrally.
     */
    private function guardCanCommit(): void
    {
        $supplier = (new SupplierRepository)->find($this->supplierId);
        abort_unless(
            $supplier !== null && $supplier->created_by_company_id === $this->currentUser()?->company_id,
            403
        );
    }

    private function chosenModel(): ?string
    {
        return in_array($this->model, self::MODELS, true) ? $this->model : null;
    }

    private function guardRow(int $id): ParsedWineData
    {
        $row = (new ParsedWineRepository)->find($id);
        abort_unless($row !== null && $row->supplier_document_id === $this->documentId, 403);
        $this->guardDocument();

        return $row;
    }

    public function render()
    {
        $document = (new SupplierDocumentRepository)->find($this->documentId);
        $supplier = (new SupplierRepository)->find($this->supplierId);

        return view('livewire.suppliers.document-review', [
            'document' => $document,
            'wines' => (new ParsedWineRepository)->paginateForDocument($this->documentId),
            'counts' => (new ParsedWineRepository)->countsForDocument($this->documentId),
            'profile' => $document === null ? null : (new SupplierParseProfileRepository)->activeForSupplier(
                $this->supplierId,
                ParseMode::forFileType($document->file_type, $document->file_name),
                $this->currentUser()?->company_id,
            ),
            'canCommit' => $supplier !== null && $supplier->created_by_company_id === $this->currentUser()?->company_id,
            'colours' => WineColour::cases(),
        ]);
    }
}
