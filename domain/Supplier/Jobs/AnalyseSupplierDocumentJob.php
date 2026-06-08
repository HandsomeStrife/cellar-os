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
 * The LLM extraction itself lives behind DocumentAnalysisService. Until that is
 * implemented the service throws and the document lands on Failed with the
 * reason recorded — proving the whole pipeline is wired.
 */
class AnalyseSupplierDocumentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $documentId) {}

    public function handle(DocumentAnalysisService $service): void
    {
        $document = (new MarkDocumentAnalysingAction)->execute($this->documentId);

        try {
            $service->analyse($document);

            (new MarkDocumentAnalysedAction)->execute($this->documentId);
        } catch (Throwable $e) {
            (new MarkDocumentFailedAction)->execute($this->documentId, $e->getMessage());
        }
    }
}
