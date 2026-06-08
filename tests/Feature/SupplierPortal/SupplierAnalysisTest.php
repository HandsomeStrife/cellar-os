<?php

declare(strict_types=1);

use Domain\Supplier\Actions\MarkDocumentAnalysedAction;
use Domain\Supplier\Actions\MarkDocumentAnalysingAction;
use Domain\Supplier\Actions\MarkDocumentFailedAction;
use Domain\Supplier\Enums\SupplierDocumentStatus;
use Domain\Supplier\Jobs\AnalyseSupplierDocumentJob;
use Domain\Supplier\Models\SupplierDocument;
use Domain\Supplier\Services\DocumentAnalysisService;

it('transitions a document through the lifecycle actions', function () {
    $document = SupplierDocument::factory()->create();

    (new MarkDocumentAnalysingAction)->execute($document->id);
    expect($document->fresh()->status)->toBe(SupplierDocumentStatus::Analysing);

    (new MarkDocumentAnalysedAction)->execute($document->id);
    expect($document->fresh()->status)->toBe(SupplierDocumentStatus::Analysed)
        ->and($document->fresh()->analysed_at)->not->toBeNull();

    (new MarkDocumentFailedAction)->execute($document->id, 'boom');
    expect($document->fresh()->status)->toBe(SupplierDocumentStatus::Failed)
        ->and($document->fresh()->analysis_notes)->toBe('boom');
});

it('runs the analysis job and lands on failed with the stub note', function () {
    // The DocumentAnalysisService is a documented stub until the LLM pipeline
    // exists, so the lifecycle currently ends at Failed — proving the wiring.
    $document = SupplierDocument::factory()->create(['status' => SupplierDocumentStatus::AwaitingAnalysis->value]);

    (new AnalyseSupplierDocumentJob($document->id))->handle(
        app(DocumentAnalysisService::class)
    );

    expect($document->fresh()->status)->toBe(SupplierDocumentStatus::Failed)
        ->and($document->fresh()->analysis_notes)->toContain('not yet implemented');
});
