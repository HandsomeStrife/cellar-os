<?php

declare(strict_types=1);

namespace Domain\Supplier\Jobs;

use Domain\Supplier\Actions\ApproveAllForDocumentAction;
use Domain\Supplier\Actions\RecordCatalogueCommitAction;
use Domain\Supplier\Enums\ParsedWineStatus;
use Domain\Supplier\Models\ParsedWine;
use Domain\Supplier\Models\SupplierDocument;
use Domain\Supplier\Support\BulkApprovalProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Bulk-approves a document's proposed wines off the request thread — a 6k-row
 * list is ~30k queries, far too much for one synchronous Livewire request.
 * Progress is mirrored to BulkApprovalProgress so the review screens can poll.
 */
class ApproveAllForDocumentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    // Large approvals run long but never retry — the approve action is
    // idempotent, but a second concurrent run would double-report progress.
    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(
        public int $documentId,
        public bool $skipFlagged = false,
        public bool $recordCommitNote = false,
    ) {}

    public function handle(): void
    {
        $document = SupplierDocument::find($this->documentId);

        if ($document === null) {
            BulkApprovalProgress::clear($this->documentId);

            return;
        }

        // The denominator for the progress display: every still-proposed row
        // this run will look at (price-less rows are counted but then skipped
        // by the action's no-price-less-commits policy).
        $total = ParsedWine::where('supplier_document_id', $this->documentId)
            ->where('status', ParsedWineStatus::Proposed->value)
            ->when($this->skipFlagged, fn ($q) => $q->whereNull('flag'))
            ->count();

        BulkApprovalProgress::start($this->documentId, $total);

        try {
            $count = (new ApproveAllForDocumentAction)->execute(
                $this->documentId,
                $this->skipFlagged,
                fn (int $approved) => BulkApprovalProgress::update($this->documentId, $approved),
            );

            if ($this->recordCommitNote) {
                (new RecordCatalogueCommitAction)->execute($document->supplier_id, $document->file_name, $count);
            }

            BulkApprovalProgress::finish($this->documentId, $count);
        } catch (Throwable $e) {
            BulkApprovalProgress::fail($this->documentId, $e->getMessage());

            throw $e;
        }
    }

    /**
     * Backstop for deaths the catch can't see (e.g. the timeout kill).
     */
    public function failed(Throwable $e): void
    {
        BulkApprovalProgress::fail($this->documentId, $e->getMessage());
    }
}
