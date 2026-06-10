<?php

declare(strict_types=1);

namespace Domain\Supplier\Jobs;

use Domain\Supplier\Actions\MarkDocumentAnalysedAction;
use Domain\Supplier\Actions\MarkDocumentAnalysingAction;
use Domain\Supplier\Actions\MarkDocumentFailedAction;
use Domain\Supplier\Services\DocumentAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Drives a supplier document through its analysis lifecycle:
 *   AwaitingAnalysis -> Analysing -> Analysed | Failed
 *
 * DocumentAnalysisService does the LLM parsing, stores the proposed wines (review
 * queue) + the learned recipe, and returns a summary; this job just moves the
 * status and records the summary (or the failure reason). Monster lists run many
 * LLM calls, so the job is allowed to run long and is not retried.
 */
class AnalyseSupplierDocumentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    // Monster lists run many LLM calls; allow a long (but finite) run. The
    // database queue's retry_after MUST exceed this or a second worker re-pops
    // the job mid-run (double cost) — see config/queue.php.
    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(
        public int $documentId,
        public bool $full = false,
        public ?string $model = null,
    ) {}

    public function handle(DocumentAnalysisService $service): void
    {
        $document = (new MarkDocumentAnalysingAction)->execute($this->documentId);

        try {
            $summary = $service->analyse($document, $this->full, $this->model);

            (new MarkDocumentAnalysedAction)->execute($this->documentId, $summary['notes']);
        } catch (Throwable $e) {
            (new MarkDocumentFailedAction)->execute($this->documentId, $e->getMessage());
        }
    }
}
