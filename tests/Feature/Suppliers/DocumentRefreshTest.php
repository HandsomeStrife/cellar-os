<?php

declare(strict_types=1);

use Domain\Catalogue\Models\Product;
use Domain\Supplier\Enums\ParseMode;
use Domain\Supplier\Enums\SupplierDocumentStatus;
use Domain\Supplier\Models\Supplier;
use Domain\Supplier\Models\SupplierDocument;
use Domain\Supplier\Models\SupplierParseProfile;
use Domain\Supplier\Repositories\SupplierDocumentRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('skips documents whose source file is unchanged', function () {
    $body = "wine,price\nChablis,12.50\n";

    $document = SupplierDocument::factory()->create([
        'source_url' => 'https://example.test/list.csv',
        'content_sha256' => hash('sha256', $body),
        'status' => SupplierDocumentStatus::Analysed->value,
    ]);

    Http::fake(['example.test/*' => Http::response($body)]);

    $this->artisan('wine:refresh-documents')
        ->expectsOutputToContain('unchanged')
        ->assertExitCode(0);

    expect(SupplierDocument::count())->toBe(1)
        ->and($document->fresh()->archived_at)->toBeNull();
});

it('archives the old edition and records a new one when the file changed', function () {
    Storage::fake('local');

    $supplier = Supplier::factory()->create();

    $document = SupplierDocument::factory()->create([
        'supplier_id' => $supplier->id,
        'file_name' => 'list-2026-01-01.csv',
        'file_type' => 'csv',
        'title' => 'Spring list',
        'source_url' => 'https://example.test/list.csv',
        'content_sha256' => hash('sha256', 'old edition'),
        'status' => SupplierDocumentStatus::Analysed->value,
    ]);

    Http::fake(['example.test/*' => Http::response("wine,price\nNew Chablis,13.00\n")]);

    $this->artisan('wine:refresh-documents')
        ->expectsOutputToContain('CHANGED')
        ->assertExitCode(0);

    $old = $document->fresh();
    $new = SupplierDocument::where('id', '!=', $document->id)->sole();

    // The old edition is kept for history, archived with a supersede pointer.
    expect($old->archived_at)->not->toBeNull()
        ->and($old->superseded_by_document_id)->toBe($new->id)
        // The new edition is the active version, awaiting analysis, carrying
        // the source forward with the new content hash and a dated filename.
        ->and($new->archived_at)->toBeNull()
        ->and($new->status)->toBe(SupplierDocumentStatus::AwaitingAnalysis)
        ->and($new->supplier_id)->toBe($supplier->id)
        ->and($new->title)->toBe('Spring list')
        ->and($new->source_url)->toBe('https://example.test/list.csv')
        ->and($new->content_sha256)->toBe(hash('sha256', "wine,price\nNew Chablis,13.00\n"))
        ->and($new->file_name)->toBe('list-'.now()->format('Y-m-d').'.csv');

    Storage::disk('local')->assertExists($new->storage_path);
});

it('reports a failure but keeps going when a download breaks', function () {
    SupplierDocument::factory()->create([
        'source_url' => 'https://broken.test/list.csv',
        'content_sha256' => hash('sha256', 'whatever'),
    ]);

    $ok = "wine,price\nChablis,12.50\n";
    SupplierDocument::factory()->create([
        'source_url' => 'https://fine.test/list.csv',
        'content_sha256' => hash('sha256', $ok),
    ]);

    Http::fake([
        'broken.test/*' => Http::response('', 500),
        'fine.test/*' => Http::response($ok),
    ]);

    $this->artisan('wine:refresh-documents')
        ->expectsOutputToContain('download failed')
        ->expectsOutputToContain('unchanged')
        ->assertExitCode(1);
});

it('processes a changed edition: refreshes kept wines, adds new ones, archives dropouts', function () {
    Storage::fake('local');

    $supplier = Supplier::factory()->create();

    // The supplier already has a learned tabular mapping, so the re-parse
    // needs no LLM at all — exactly the cheap weekly-refresh path.
    SupplierParseProfile::create([
        'supplier_id' => $supplier->id,
        'company_id' => null,
        'mode' => ParseMode::Tabular->value,
        'recipe' => ['mapping' => [
            'wine_name' => 'Wine', 'vintage' => 'Vintage', 'unit_price' => 'Price',
            'country' => 'Country', 'colour' => 'Colour',
        ]],
        'confidence' => 0.95,
        'is_active' => true,
    ]);

    $oldDoc = SupplierDocument::factory()->create([
        'supplier_id' => $supplier->id,
        'file_name' => 'list.csv',
        'file_type' => 'csv',
        'source_url' => 'https://example.test/list.csv',
        'content_sha256' => hash('sha256', 'old edition'),
        'status' => SupplierDocumentStatus::Analysed->value,
    ]);

    $kept = Product::factory()->create([
        'supplier_id' => $supplier->id,
        'wine_name' => 'Chablis Premier Cru',
        'vintage' => 2022,
        'format_ml' => 750,
        'unit_price' => '15.00',
        'source_document_id' => $oldDoc->id,
    ]);
    $dropped = Product::factory()->create([
        'supplier_id' => $supplier->id,
        'wine_name' => 'Discontinued Barolo',
        'vintage' => 2018,
        'format_ml' => 750,
        'source_document_id' => $oldDoc->id,
    ]);

    Http::fake(['example.test/*' => Http::response(
        "Wine,Vintage,Price,Country,Colour\n"
        ."Chablis Premier Cru,2022,16.50,France,White\n"
        ."Brand New Rioja,2021,11.00,Spain,Red\n"
    )]);

    $this->artisan('wine:refresh-documents', ['--process' => true, '--approve' => true])
        ->expectsOutputToContain('CHANGED')
        ->expectsOutputToContain('archived 1 dropped-out wine(s)')
        ->assertExitCode(0);

    $newDoc = SupplierDocument::where('id', '!=', $oldDoc->id)->sole();

    // Kept wine: price refreshed in place, provenance moved to the new edition.
    expect($kept->fresh()->unit_price)->toBe('16.50')
        ->and($kept->fresh()->archived_at)->toBeNull()
        ->and($kept->fresh()->source_document_id)->toBe($newDoc->id)
        // New wine committed.
        ->and(Product::where('wine_name', 'Brand New Rioja')->exists())->toBeTrue()
        // Dropped wine archived, not deleted.
        ->and($dropped->fresh()->archived_at)->not->toBeNull();
});

it('re-derives country after a re-import that blanks it, and records provenance', function () {
    Storage::fake('local');

    $supplier = Supplier::factory()->create();
    SupplierParseProfile::create([
        'supplier_id' => $supplier->id, 'company_id' => null, 'mode' => ParseMode::Tabular->value,
        'recipe' => ['mapping' => ['wine_name' => 'Wine', 'region' => 'Region', 'unit_price' => 'Price']],
        'confidence' => 0.95, 'is_active' => true,
    ]);

    $oldDoc = SupplierDocument::factory()->create([
        'supplier_id' => $supplier->id, 'file_name' => 'list.csv', 'file_type' => 'csv',
        'source_url' => 'https://example.test/list.csv', 'content_sha256' => hash('sha256', 'old'),
        'status' => SupplierDocumentStatus::Analysed->value,
    ]);

    // New edition: a region but NO country column (like Farr's export).
    Http::fake(['example.test/*' => Http::response("Wine,Region,Price\nChambolle-Musigny,Bourgogne,42.00\n")]);

    $this->artisan('wine:refresh-documents', ['--process' => true, '--approve' => true])
        ->expectsOutputToContain('Backfilled filterable columns')
        ->assertExitCode(0);

    $newDoc = SupplierDocument::where('id', '!=', $oldDoc->id)->sole();
    $product = Product::where('supplier_id', $supplier->id)->where('wine_name', 'Chambolle-Musigny')->sole();

    // Country was absent in the file but derived from the region by the backfill.
    expect($product->country)->toBe('France')
        ->and($product->region)->toBe('Bourgogne')
        // Refresh now records provenance like the job/CLI do.
        ->and($newDoc->fresh()->status)->toBe(SupplierDocumentStatus::Analysed)
        ->and($newDoc->fresh()->analysis_notes)->not->toBeNull();
});

it('only refreshes current documents that carry a source url', function () {
    SupplierDocument::factory()->create(['source_url' => null]);
    SupplierDocument::factory()->create([
        'source_url' => 'https://example.test/list.csv',
        'archived_at' => now(),
    ]);
    $live = SupplierDocument::factory()->create(['source_url' => 'https://example.test/live.csv']);

    $refreshable = (new SupplierDocumentRepository)->refreshable();

    expect($refreshable)->toHaveCount(1)
        ->and($refreshable->first()->id)->toBe($live->id);
});
