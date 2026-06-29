<?php

declare(strict_types=1);

use App\Livewire\Suppliers\DocumentReview;
use Domain\Catalogue\Enums\SellingUnit;
use Domain\Catalogue\Models\Product;
use Domain\Supplier\Actions\ConnectCompanyToSupplierAction;
use Domain\Supplier\Enums\ParsedWineFlag;
use Domain\Supplier\Enums\SupplierDocumentStatus;
use Domain\Supplier\Models\ParsedWine;
use Domain\Supplier\Models\Supplier;
use Domain\Supplier\Models\SupplierDocument;
use Domain\Supplier\Services\DocumentTextExtractor;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\FakeClaudeClient;
use Tests\Support\FakeDocumentTextExtractor;

// runAnalysis() / fakeClaude() are defined in DocumentParsingTest.php (same suite).

it('reads a case price-basis column and derives the canonical per-bottle price (tabular)', function () {
    Storage::fake('local');
    $supplier = Supplier::factory()->create();
    $csv = "Wine,Price,Sold As,Pack\n"
        ."Big Red,120.00,case,6\n"
        ."Small White,15.00,bottle,6\n";
    Storage::disk('local')->put('supplier-documents/list.csv', $csv);

    $doc = SupplierDocument::factory()->create([
        'supplier_id' => $supplier->id, 'file_name' => 'list.csv', 'file_type' => 'text/csv',
        'storage_path' => 'supplier-documents/list.csv', 'status' => SupplierDocumentStatus::AwaitingAnalysis->value,
    ]);

    fakeClaude(new FakeClaudeClient(mapping: [
        'wine_name' => 'Wine', 'unit_price' => 'Price', 'price_basis' => 'Sold As', 'case_size' => 'Pack',
    ]));

    runAnalysis($doc->id);

    $caseWine = ParsedWine::where('supplier_document_id', $doc->id)->get()->firstWhere('payload.wine_name', 'Big Red');
    $bottleWine = ParsedWine::where('supplier_document_id', $doc->id)->get()->firstWhere('payload.wine_name', 'Small White');

    expect($caseWine->payload['sold_by'])->toBe('case')
        ->and($caseWine->payload['pack_price'])->toBe('120.00')
        ->and($caseWine->payload['unit_price'])->toBe('20.00')        // 120 / 6
        ->and($bottleWine->payload['sold_by'])->toBe('bottle')
        ->and($bottleWine->payload['pack_price'])->toBeNull()
        ->and($bottleWine->payload['unit_price'])->toBe('15.00');
});

it('maps a separate per-case price column to pack_price and keeps the per-bottle price (tabular)', function () {
    Storage::fake('local');
    $supplier = Supplier::factory()->create();
    $csv = "Wine,Bottle Price,Case Price\nBig Red,20.00,118.00\n";
    Storage::disk('local')->put('supplier-documents/list.csv', $csv);

    $doc = SupplierDocument::factory()->create([
        'supplier_id' => $supplier->id, 'file_name' => 'list.csv', 'file_type' => 'text/csv',
        'storage_path' => 'supplier-documents/list.csv', 'status' => SupplierDocumentStatus::AwaitingAnalysis->value,
    ]);

    fakeClaude(new FakeClaudeClient(mapping: [
        'wine_name' => 'Wine', 'unit_price' => 'Bottle Price', 'pack_price' => 'Case Price',
    ]));

    runAnalysis($doc->id);

    $wine = ParsedWine::where('supplier_document_id', $doc->id)->first();
    expect($wine->payload['sold_by'])->toBe('case')
        ->and($wine->payload['pack_price'])->toBe('118.00')
        ->and($wine->payload['unit_price'])->toBe('20.00');
});

it('honours a per-wine case price_basis from the LLM extraction (PDF)', function () {
    Storage::fake('local');
    $supplier = Supplier::factory()->create();
    Storage::disk('local')->put('supplier-documents/list.pdf', 'dummy');

    $doc = SupplierDocument::factory()->create([
        'supplier_id' => $supplier->id, 'file_name' => 'list.pdf', 'file_type' => 'application/pdf',
        'storage_path' => 'supplier-documents/list.pdf', 'status' => SupplierDocumentStatus::AwaitingAnalysis->value,
    ]);

    app()->instance(DocumentTextExtractor::class, new FakeDocumentTextExtractor(pages: 2));
    fakeClaude(new FakeClaudeClient(wines: [
        ['wine_name' => 'Alabaster', 'producer' => 'Teso La Monja', 'unit_price' => '1275.00', 'price_basis' => 'case', 'case_size' => '6', 'country' => 'Spain'],
    ], section: ['country' => 'Spain']));

    runAnalysis($doc->id);

    $wine = ParsedWine::where('supplier_document_id', $doc->id)->first();
    expect($wine->payload['sold_by'])->toBe('case')
        ->and($wine->payload['pack_price'])->toBe('1275.00')
        ->and($wine->payload['unit_price'])->toBe('212.50');         // 1275 / 6
});

it('flags a bottle-priced row whose text hints at case pricing for human review', function () {
    Storage::fake('local');
    $supplier = Supplier::factory()->create();
    Storage::disk('local')->put('supplier-documents/list.pdf', 'dummy');

    $doc = SupplierDocument::factory()->create([
        'supplier_id' => $supplier->id, 'file_name' => 'list.pdf', 'file_type' => 'application/pdf',
        'storage_path' => 'supplier-documents/list.pdf', 'status' => SupplierDocumentStatus::AwaitingAnalysis->value,
    ]);

    app()->instance(DocumentTextExtractor::class, new FakeDocumentTextExtractor(pages: 2));
    fakeClaude(new FakeClaudeClient(wines: [
        ['wine_name' => 'Mystery Cuvée per case', 'unit_price' => '90.00', 'country' => 'France'],
        ['wine_name' => 'Plain Bottle', 'unit_price' => '12.00', 'country' => 'France'],
    ], section: ['country' => 'France']));

    runAnalysis($doc->id);

    $wines = ParsedWine::where('supplier_document_id', $doc->id)->get();
    expect($wines->firstWhere('payload.wine_name', 'Mystery Cuvée per case')->flag)->toBe(ParsedWineFlag::AmbiguousPricing->value)
        ->and($wines->firstWhere('payload.wine_name', 'Plain Bottle')->flag)->toBeNull();
});

it('lets a reviewer mark a wine as sold by the case and approve it into the catalogue', function () {
    [$company, $user] = makeTenant();
    $supplier = Supplier::factory()->create(['created_by_company_id' => $company->id]);
    (new ConnectCompanyToSupplierAction)->execute($company->id, $supplier->id);
    $doc = SupplierDocument::factory()->create([
        'supplier_id' => $supplier->id, 'uploaded_by_company_id' => $company->id,
        'status' => SupplierDocumentStatus::Analysed->value,
    ]);
    $payload = Product::factory()->make([
        'wine_name' => 'Alabaster', 'unit_price' => '212.50', 'sold_by' => 'bottle',
    ])->getData()->toArray();
    $wine = ParsedWine::factory()->create([
        'supplier_document_id' => $doc->id, 'supplier_id' => $supplier->id,
        'payload' => $payload,
        'flag' => ParsedWineFlag::AmbiguousPricing->value,
    ]);

    $this->actingAs($user);

    Livewire::test(DocumentReview::class, ['uuid' => $supplier->uuid, 'documentId' => $doc->id])
        ->call('startEdit', $wine->id)
        ->set('edit.sold_by', 'case')
        ->set('edit.case_size', '6')
        ->set('edit.pack_price', '1275')
        ->call('saveEdit')
        ->call('approve', $wine->id);

    $product = Product::where('supplier_id', $supplier->id)->where('wine_name', 'Alabaster')->firstOrFail();
    expect($product->sold_by)->toBe(SellingUnit::Case)
        ->and((float) $product->pack_price)->toBe(1275.0)
        ->and($product->case_size)->toBe(6);
});

it('gives the ambiguous-pricing flag a review label and colour', function () {
    expect(ParsedWineFlag::AmbiguousPricing->getLabel())->toBe('Check case vs bottle')
        ->and(ParsedWineFlag::AmbiguousPricing->getColour())->toBe('amber');
});
