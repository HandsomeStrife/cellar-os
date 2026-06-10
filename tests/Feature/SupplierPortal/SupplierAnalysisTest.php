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

it('fails closed when the underlying file is missing', function () {
    // The analysis job is wired to DocumentAnalysisService; if the stored file
    // can't be read it lands on Failed with the reason recorded.
    $document = SupplierDocument::factory()->create([
        'status' => SupplierDocumentStatus::AwaitingAnalysis->value,
        'storage_path' => 'supplier-documents/does-not-exist.csv',
    ]);

    (new AnalyseSupplierDocumentJob($document->id))->handle(
        app(DocumentAnalysisService::class)
    );

    expect($document->fresh()->status)->toBe(SupplierDocumentStatus::Failed)
        ->and($document->fresh()->analysis_notes)->toContain('could not be found');
});
