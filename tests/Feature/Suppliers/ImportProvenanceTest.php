<?php

declare(strict_types=1);

use Domain\Supplier\Actions\RecordCatalogueCommitAction;
use Domain\Supplier\Actions\RecordDocumentAnalysisAction;
use Domain\Supplier\Enums\SupplierDocumentStatus;
use Domain\Supplier\Models\Supplier;
use Domain\Supplier\Models\SupplierDocument;
use Domain\Supplier\Models\SupplierNote;

it('records analysis on the document and as a history note', function () {
    $supplier = Supplier::factory()->create();
    $document = SupplierDocument::factory()->create([
        'supplier_id' => $supplier->id,
        'file_name' => 'spring-list.pdf',
        'status' => SupplierDocumentStatus::Analysing->value,
    ]);

    (new RecordDocumentAnalysisAction)->execute($document->id, [
        'strategy' => 'llm',
        'stored' => 250,
        'flagged' => 3,
        'preview' => false,
        'input_tokens' => 90000,
        'output_tokens' => 25000,
        'cost_usd' => 0.2164,
        'model' => 'claude-haiku-4-5-20251001',
        'notes' => 'Parsed 250 wine(s), 3 flagged for review. Extracted across 116 page(s).',
    ]);

    $fresh = $document->fresh();
    expect($fresh->status)->toBe(SupplierDocumentStatus::Analysed)
        ->and($fresh->analysed_at)->not->toBeNull()
        ->and($fresh->analysis_notes)->toContain('Parsed 250 wine(s)');

    $note = SupplierNote::where('supplier_id', $supplier->id)->latest('id')->first();
    expect($note)->not->toBeNull()
        ->and($note->note)->toContain('Document analysed: spring-list.pdf')
        ->and($note->note)->toContain('LLM extraction')
        ->and($note->note)->toContain('250 wine(s)')
        ->and($note->note)->toContain('$0.2164');
});

it('labels deterministic parses as free in the history note', function () {
    $supplier = Supplier::factory()->create();
    $document = SupplierDocument::factory()->create(['supplier_id' => $supplier->id, 'file_name' => 'stock.csv']);

    (new RecordDocumentAnalysisAction)->execute($document->id, [
        'strategy' => 'pattern', 'stored' => 565, 'flagged' => 0, 'preview' => false,
        'input_tokens' => 7000, 'output_tokens' => 400, 'cost_usd' => 0.0092,
        'model' => 'claude-haiku-4-5-20251001', 'notes' => 'Pattern-parsed deterministically.',
    ]);

    $note = SupplierNote::where('supplier_id', $supplier->id)->latest('id')->first();
    expect($note->note)->toContain('re-imports are free');
});

it('writes a catalogue-commit note, including archived dropouts on refresh', function () {
    $supplier = Supplier::factory()->create();

    (new RecordCatalogueCommitAction)->execute($supplier->id, 'edition-2.csv', committed: 120, archived: 8, refresh: true);

    $note = SupplierNote::where('supplier_id', $supplier->id)->latest('id')->first();
    expect($note->note)->toContain('Catalogue refreshed from edition-2.csv')
        ->and($note->note)->toContain('120 wine(s) committed')
        ->and($note->note)->toContain('8 archived');
});

it('writes no commit note when nothing changed', function () {
    $supplier = Supplier::factory()->create();

    (new RecordCatalogueCommitAction)->execute($supplier->id, 'unchanged.csv', committed: 0, archived: 0);

    expect(SupplierNote::where('supplier_id', $supplier->id)->count())->toBe(0);
});
