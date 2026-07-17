<?php

declare(strict_types=1);

use App\Livewire\Suppliers\DocumentReview;
use Domain\Catalogue\Models\Product;
use Domain\Company\Models\Company;
use Domain\Supplier\Actions\ApproveAllForDocumentAction;
use Domain\Supplier\Actions\ConnectCompanyToSupplierAction;
use Domain\Supplier\Enums\ParsedWineStatus;
use Domain\Supplier\Enums\ParseMode;
use Domain\Supplier\Enums\SupplierDocumentStatus;
use Domain\Supplier\Jobs\AnalyseSupplierDocumentJob;
use Domain\Supplier\Models\ParsedWine;
use Domain\Supplier\Models\Supplier;
use Domain\Supplier\Models\SupplierDocument;
use Domain\Supplier\Models\SupplierParseProfile;
use Domain\Supplier\Repositories\SupplierParseProfileRepository;
use Domain\Supplier\Services\ClaudeClient;
use Domain\Supplier\Services\DocumentAnalysisService;
use Domain\Supplier\Services\DocumentTextExtractor;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\FakeClaudeClient;
use Tests\Support\FakeDocumentTextExtractor;

function fakeClaude(FakeClaudeClient $client): void
{
    app()->instance(ClaudeClient::class, $client);
}

function runAnalysis(int $documentId, bool $full = true): array
{
    return (new AnalyseSupplierDocumentJob($documentId, full: $full))
        ->handle(app(DocumentAnalysisService::class)) ?? [];
}

// ---------------------------------------------------------------- tabular ----

it('parses a spreadsheet into proposed wines and learns a column mapping', function () {
    Storage::fake('local');
    $supplier = Supplier::factory()->create();
    $csv = "Wine,Vintage,Price,Country,Colour\n"
        ."Chablis Premier Cru,2022,15.00,France,White\n"
        ."Barolo Riserva,2019,30.00,Italy,Red\n"
        ."FRANCE,,,,\n"                       // a section heading → dropped
        ."No Price Wine,2021,,Spain,Red\n";   // missing price → flagged, kept
    Storage::disk('local')->put('supplier-documents/list.csv', $csv);

    $doc = SupplierDocument::factory()->create([
        'supplier_id' => $supplier->id,
        'file_name' => 'list.csv',
        'file_type' => 'text/csv',
        'storage_path' => 'supplier-documents/list.csv',
        'status' => SupplierDocumentStatus::AwaitingAnalysis->value,
    ]);

    fakeClaude(new FakeClaudeClient(mapping: [
        'wine_name' => 'Wine', 'vintage' => 'Vintage', 'unit_price' => 'Price',
        'country' => 'Country', 'colour' => 'Colour',
    ]));

    runAnalysis($doc->id);

    expect($doc->fresh()->status)->toBe(SupplierDocumentStatus::Analysed);

    $wines = ParsedWine::where('supplier_document_id', $doc->id)->get();
    expect($wines)->toHaveCount(3); // FRANCE heading dropped
    expect($wines->pluck('payload.wine_name')->all())->toContain('Chablis Premier Cru', 'Barolo Riserva', 'No Price Wine')
        ->and($wines->pluck('payload.wine_name')->all())->not->toContain('FRANCE');

    // The "No Price Wine" row is flagged for review.
    expect($wines->firstWhere('payload.wine_name', 'No Price Wine')->flag)->toBe('missing_price');

    // The mapping is stored as the supplier's reusable recipe + the import wizard's mapping.
    $profile = SupplierParseProfile::where('supplier_id', $supplier->id)->first();
    expect($profile->mode)->toBe(ParseMode::Tabular)
        ->and($profile->recipe['mapping']['wine_name'])->toBe('Wine');
    expect($supplier->fresh()->column_mapping['unit_price'])->toBe('Price');
});

it('reuses a saved mapping on the next upload instead of re-deriving it', function () {
    Storage::fake('local');
    $supplier = Supplier::factory()->create();
    $csv = "Wine,Price\nChablis,15.00\n";
    Storage::disk('local')->put('supplier-documents/a.csv', $csv);
    Storage::disk('local')->put('supplier-documents/b.csv', $csv);

    $make = fn (string $name) => SupplierDocument::factory()->create([
        'supplier_id' => $supplier->id, 'file_name' => $name, 'file_type' => 'text/csv',
        'storage_path' => "supplier-documents/$name", 'status' => SupplierDocumentStatus::AwaitingAnalysis->value,
    ]);

    fakeClaude(new FakeClaudeClient(mapping: ['wine_name' => 'Wine', 'unit_price' => 'Price']));
    runAnalysis($make('a.csv')->id);

    // Second run: the recipe fits the same headers, so Claude must NOT be asked again.
    $second = new FakeClaudeClient(mapping: ['wine_name' => 'Wine', 'unit_price' => 'Price'], failIfMappingDerived: true);
    fakeClaude($second);
    runAnalysis($make('b.csv')->id);

    expect($second->deriveMappingCalls)->toBe(0);
});

// --------------------------------------------------------------- document ----

it('parses a PDF via chunked extraction and learns a document recipe', function () {
    Storage::fake('local');
    $supplier = Supplier::factory()->create();
    Storage::disk('local')->put('supplier-documents/list.pdf', 'dummy');

    $doc = SupplierDocument::factory()->create([
        'supplier_id' => $supplier->id, 'file_name' => 'list.pdf', 'file_type' => 'application/pdf',
        'storage_path' => 'supplier-documents/list.pdf', 'status' => SupplierDocumentStatus::AwaitingAnalysis->value,
    ]);

    app()->instance(DocumentTextExtractor::class, new FakeDocumentTextExtractor(pages: 2));
    fakeClaude(new FakeClaudeClient(wines: [
        ['wine_name' => 'Koppitsch Homok', 'vintage' => '2024', 'colour' => 'White', 'unit_price' => '14.35', 'country' => 'Austria'],
        ['wine_name' => 'Koppitsch Rét', 'vintage' => '2023', 'colour' => 'Red', 'unit_price' => '14.08', 'country' => 'Austria'],
    ], section: ['country' => 'Austria']));

    runAnalysis($doc->id);

    expect($doc->fresh()->status)->toBe(SupplierDocumentStatus::Analysed);
    $wines = ParsedWine::where('supplier_document_id', $doc->id)->get();
    expect($wines)->toHaveCount(2)
        ->and($wines->first()->payload['colour'])->toBe('White'); // normalised to enum value

    expect(SupplierParseProfile::where('supplier_id', $supplier->id)->first()->mode)->toBe(ParseMode::Document);
});

it('flags spirits and sake as non-wine and colours vermouth fortified', function () {
    Storage::fake('local');
    $supplier = Supplier::factory()->create();
    Storage::disk('local')->put('supplier-documents/list.pdf', 'dummy');

    $doc = SupplierDocument::factory()->create([
        'supplier_id' => $supplier->id, 'file_name' => 'list.pdf', 'file_type' => 'application/pdf',
        'storage_path' => 'supplier-documents/list.pdf', 'status' => SupplierDocumentStatus::AwaitingAnalysis->value,
    ]);

    app()->instance(DocumentTextExtractor::class, new FakeDocumentTextExtractor(pages: 2));
    fakeClaude(new FakeClaudeClient(wines: [
        ['wine_name' => 'VSOP Grande Champagne', 'producer' => 'Maxime Trijol - Cognac', 'unit_price' => '45.00'],
        ['wine_name' => 'Junmai Ginjo', 'producer' => 'Kanpai', 'unit_price' => '25.00'],
        ['wine_name' => 'Vermouth Naturale Rosso', 'producer' => 'Societa Agricola', 'unit_price' => '19.00'],
        ['wine_name' => 'Barolo Cerretta', 'producer' => 'Brovia', 'colour' => 'Red', 'unit_price' => '60.00'],
    ]));

    runAnalysis($doc->id);

    $byName = ParsedWine::where('supplier_document_id', $doc->id)->get()
        ->keyBy(fn ($w) => $w->payload['wine_name']);

    expect($byName['VSOP Grande Champagne']->flag)->toBe('non_wine')
        ->and($byName['Junmai Ginjo']->flag)->toBe('non_wine')
        ->and($byName['Vermouth Naturale Rosso']->flag)->toBeNull()
        ->and($byName['Vermouth Naturale Rosso']->payload['colour'])->toBe('Fortified')
        ->and($byName['Barolo Cerretta']->flag)->toBeNull();

    // Bulk approve must leave the flagged spirits out of the catalogue.
    (new ApproveAllForDocumentAction)->execute($doc->id, skipFlagged: true);
    expect(Product::where('supplier_id', $supplier->id)->pluck('wine_name')->all())
        ->toBe(['Vermouth Naturale Rosso', 'Barolo Cerretta']);
});

it('only previews the first chunk of a large PDF until the full run is confirmed', function () {
    Storage::fake('local');
    $supplier = Supplier::factory()->create();
    Storage::disk('local')->put('supplier-documents/big.pdf', 'dummy');

    $doc = SupplierDocument::factory()->create([
        'supplier_id' => $supplier->id, 'file_name' => 'big.pdf', 'file_type' => 'application/pdf',
        'storage_path' => 'supplier-documents/big.pdf', 'status' => SupplierDocumentStatus::AwaitingAnalysis->value,
    ]);

    app()->instance(DocumentTextExtractor::class, new FakeDocumentTextExtractor(pages: 30));
    $fake = new FakeClaudeClient(wines: [['wine_name' => 'Some Wine', 'unit_price' => '12.00']]);
    fakeClaude($fake);

    // Preview (full:false) on a 30-page PDF → only the first 5-page chunk runs.
    runAnalysis($doc->id, full: false);

    expect($fake->extractCalls)->toBe(1)
        ->and($doc->fresh()->analysis_notes)->toContain('Preview');
});

it('studies a structured PDF once, then pattern-parses the WHOLE document with zero extraction tokens', function () {
    Storage::fake('local');
    $supplier = Supplier::factory()->create();
    Storage::disk('local')->put('supplier-documents/big.pdf', 'dummy');

    $doc = SupplierDocument::factory()->create([
        'supplier_id' => $supplier->id, 'file_name' => 'big.pdf', 'file_type' => 'application/pdf',
        'storage_path' => 'supplier-documents/big.pdf', 'status' => SupplierDocumentStatus::AwaitingAnalysis->value,
    ]);

    // A 30-page "monster" whose rows match machine rules (Raeburn-style zones).
    $coordRows = [];
    foreach ([1, 15, 30] as $page) {
        $coordRows[] = [
            'page' => $page, 'y' => 100.0,
            'cells' => [
                ['text' => "Wine {$page}", 'x0' => 260.0, 'x1' => 300.0],
                ['text' => 'r', 'x0' => 444.0, 'x1' => 450.0],
                ['text' => '2021', 'x0' => 464.0, 'x1' => 480.0],
                ['text' => '75/6', 'x0' => 489.0, 'x1' => 500.0],
            ],
        ];
    }

    app()->instance(DocumentTextExtractor::class, new FakeDocumentTextExtractor(pages: 30, rows: $coordRows));
    $fake = new FakeClaudeClient;
    $fake->rules = [
        'zones' => [
            ['field' => 'wine_name', 'x_min' => '250', 'x_max' => '430'],
            ['field' => 'colour', 'x_min' => '430', 'x_max' => '460'],
            ['field' => 'vintage', 'x_min' => '460', 'x_max' => '485'],
            ['field' => 'format_ml', 'x_min' => '485', 'x_max' => '540'],
        ],
        'require' => ['wine_name', 'format_ml'],
        'colour_map' => [['code' => 'r', 'colour' => 'Red']],
        'format_unit' => 'cl',
    ];
    fakeClaude($fake);

    // Even as a "preview" request (full:false), pattern parsing runs the whole
    // document — it costs nothing.
    runAnalysis($doc->id, full: false);

    expect($doc->fresh()->status)->toBe(SupplierDocumentStatus::Analysed)
        ->and($doc->fresh()->analysis_notes)->toContain('Pattern-parsed')
        ->and($fake->extractCalls)->toBe(0)        // ZERO LLM extraction calls
        ->and($fake->deriveRulesCalls)->toBe(1);   // one study call

    $wines = ParsedWine::where('supplier_document_id', $doc->id)->get();
    expect($wines)->toHaveCount(3)
        ->and($wines->first()->payload['colour'])->toBe('Red')
        ->and($wines->first()->payload['format_ml'])->toBe(750)   // 75/6 → 75cl → 750ml
        ->and($wines->first()->payload['case_size'])->toBe(6)
        ->and($wines->first()->source_ref)->toBe('p1')
        ->and($wines->last()->source_ref)->toBe('p30');

    // The reusable recipe stores the machine rules.
    $profile = SupplierParseProfile::where('supplier_id', $supplier->id)->first();
    expect($profile->recipe['strategy'])->toBe('pattern')
        ->and($profile->recipe['rules']['format_unit'])->toBe('cl');

    // A second document reuses the rules without re-studying.
    Storage::disk('local')->put('supplier-documents/big2.pdf', 'dummy');
    $doc2 = SupplierDocument::factory()->create([
        'supplier_id' => $supplier->id, 'file_name' => 'big2.pdf', 'file_type' => 'application/pdf',
        'storage_path' => 'supplier-documents/big2.pdf', 'status' => SupplierDocumentStatus::AwaitingAnalysis->value,
    ]);
    runAnalysis($doc2->id);
    expect($fake->deriveRulesCalls)->toBe(1)
        ->and(ParsedWine::where('supplier_document_id', $doc2->id)->count())->toBe(3);
});

it('falls back to LLM extraction when learned rules match nothing on a new document', function () {
    Storage::fake('local');
    $supplier = Supplier::factory()->create();
    Storage::disk('local')->put('supplier-documents/odd.pdf', 'dummy');

    $doc = SupplierDocument::factory()->create([
        'supplier_id' => $supplier->id, 'file_name' => 'odd.pdf', 'file_type' => 'application/pdf',
        'storage_path' => 'supplier-documents/odd.pdf', 'status' => SupplierDocumentStatus::AwaitingAnalysis->value,
    ]);

    // An existing pattern recipe whose zones don't fit this document at all.
    SupplierParseProfile::factory()->create([
        'supplier_id' => $supplier->id, 'company_id' => null, 'mode' => ParseMode::Document->value,
        'recipe' => ['strategy' => 'pattern', 'rules' => [
            'zones' => [['field' => 'wine_name', 'x_min' => '900', 'x_max' => '999']],
            'require' => ['wine_name'],
        ]],
    ]);

    // Coordinate rows all OUTSIDE the zone → zero matches → LLM fallback.
    $coordRows = [['page' => 1, 'y' => 10.0, 'cells' => [['text' => 'Some Wine £9.00', 'x0' => 50.0, 'x1' => 90.0]]]];
    app()->instance(DocumentTextExtractor::class, new FakeDocumentTextExtractor(pages: 2, rows: $coordRows));
    $fake = new FakeClaudeClient(wines: [['wine_name' => 'Fallback Wine', 'unit_price' => '9.00']]);
    fakeClaude($fake);

    runAnalysis($doc->id);

    expect($fake->extractCalls)->toBeGreaterThan(0);
    expect(ParsedWine::where('supplier_document_id', $doc->id)->get()->pluck('payload.wine_name')->all())->toContain('Fallback Wine');
    // The recipe was re-learned as an LLM profile.
    expect(SupplierParseProfile::where('supplier_id', $supplier->id)->where('is_active', true)->first()->recipe['strategy'])->toBe('llm');
});

// ----------------------------------------------------------------- review ----

it('approves proposed wines into the catalogue (idempotent) via the review screen', function () {
    [$company, $user] = makeTenant();
    $supplier = Supplier::factory()->create(['created_by_company_id' => $company->id]);
    (new ConnectCompanyToSupplierAction)->execute($company->id, $supplier->id);
    $doc = SupplierDocument::factory()->create([
        'supplier_id' => $supplier->id, 'uploaded_by_company_id' => $company->id,
        'status' => SupplierDocumentStatus::Analysed->value,
    ]);
    ParsedWine::factory()->count(2)->create(['supplier_document_id' => $doc->id, 'supplier_id' => $supplier->id]);

    $this->actingAs($user);

    Livewire::test(DocumentReview::class, ['uuid' => $supplier->uuid, 'documentId' => $doc->id])
        ->call('approveAll');

    expect(Product::where('supplier_id', $supplier->id)->count())->toBe(2);
    expect(ParsedWine::where('status', ParsedWineStatus::Approved->value)->count())->toBe(2);

    // Re-approving doesn't duplicate (idempotent upsert).
    Livewire::test(DocumentReview::class, ['uuid' => $supplier->uuid, 'documentId' => $doc->id])->call('approveAll');
    expect(Product::where('supplier_id', $supplier->id)->count())->toBe(2);
});

it('classifies files by extension first (a PDF named "sheet" stays a PDF)', function () {
    expect(ParseMode::forFileType('application/pdf', 'price-sheet.pdf'))->toBe(ParseMode::Document)
        ->and(ParseMode::forFileType(null, 'datasheet.pdf'))->toBe(ParseMode::Document)
        ->and(ParseMode::forFileType('text/csv', 'list.csv'))->toBe(ParseMode::Tabular)
        ->and(ParseMode::forFileType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'list.xlsx'))->toBe(ParseMode::Tabular);
});

it('forbids a buyer approving wines into a shared supplier\'s catalogue', function () {
    [$company, $user] = makeTenant();
    // A Listed (public/shared) supplier the company is connected to.
    $shared = Supplier::factory()->create();
    (new ConnectCompanyToSupplierAction)->execute($company->id, $shared->id);
    $doc = SupplierDocument::factory()->create([
        'supplier_id' => $shared->id, 'uploaded_by_company_id' => $company->id,
        'status' => SupplierDocumentStatus::Analysed->value,
    ]);
    $wine = ParsedWine::factory()->create(['supplier_document_id' => $doc->id, 'supplier_id' => $shared->id]);

    $this->actingAs($user);

    Livewire::test(DocumentReview::class, ['uuid' => $shared->uuid, 'documentId' => $doc->id])
        ->call('approve', $wine->id)
        ->assertForbidden();
    Livewire::test(DocumentReview::class, ['uuid' => $shared->uuid, 'documentId' => $doc->id])
        ->call('approveAll')
        ->assertForbidden();

    // The shared catalogue is untouched.
    expect(Product::where('supplier_id', $shared->id)->count())->toBe(0);
});

it('forbids a buyer reviewing a supplier-portal-uploaded document', function () {
    [$company, $user] = makeTenant();
    $supplier = Supplier::factory()->create();
    (new ConnectCompanyToSupplierAction)->execute($company->id, $supplier->id);
    // Portal upload: uploaded_by_company_id is NULL.
    $doc = SupplierDocument::factory()->create([
        'supplier_id' => $supplier->id, 'uploaded_by_company_id' => null,
        'status' => SupplierDocumentStatus::Analysed->value,
    ]);

    $this->actingAs($user);

    Livewire::test(DocumentReview::class, ['uuid' => $supplier->uuid, 'documentId' => $doc->id])->assertForbidden();
});

it('preserves approved wines across a re-analysis', function () {
    Storage::fake('local');
    $supplier = Supplier::factory()->create();
    Storage::disk('local')->put('supplier-documents/list.csv', "Wine,Price\nNew Wine,12.00\n");
    $doc = SupplierDocument::factory()->create([
        'supplier_id' => $supplier->id, 'file_name' => 'list.csv', 'file_type' => 'text/csv',
        'storage_path' => 'supplier-documents/list.csv', 'status' => SupplierDocumentStatus::Analysed->value,
    ]);
    $approved = ParsedWine::factory()->create([
        'supplier_document_id' => $doc->id, 'supplier_id' => $supplier->id,
        'status' => ParsedWineStatus::Approved->value,
    ]);
    $proposed = ParsedWine::factory()->create(['supplier_document_id' => $doc->id, 'supplier_id' => $supplier->id]);

    fakeClaude(new FakeClaudeClient(mapping: ['wine_name' => 'Wine', 'unit_price' => 'Price']));
    runAnalysis($doc->id);

    // The approved row (audit trail / recipe examples) survives; the stale proposal is replaced.
    $this->assertDatabaseHas('parsed_wines', ['id' => $approved->id, 'status' => 'approved']);
    $this->assertDatabaseMissing('parsed_wines', ['id' => $proposed->id]);
    expect(ParsedWine::where('supplier_document_id', $doc->id)->where('status', 'proposed')->count())->toBe(1);
});

it('keeps a buyer\'s learned recipe scoped to their company', function () {
    $supplier = Supplier::factory()->create();
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    SupplierParseProfile::factory()->create([
        'supplier_id' => $supplier->id, 'company_id' => $companyA->id,
        'recipe' => ['mapping' => ['wine_name' => 'A-Wine']],
    ]);
    SupplierParseProfile::factory()->create([
        'supplier_id' => $supplier->id, 'company_id' => null,
        'recipe' => ['mapping' => ['wine_name' => 'Global-Wine']],
    ]);

    $repo = new SupplierParseProfileRepository;

    // Company A gets its own profile; company B falls back to the global one,
    // never to A's; portal/admin (null) only sees the global profile.
    expect($repo->activeForSupplier($supplier->id, ParseMode::Tabular, $companyA->id)->recipe['mapping']['wine_name'])->toBe('A-Wine')
        ->and($repo->activeForSupplier($supplier->id, ParseMode::Tabular, $companyB->id)->recipe['mapping']['wine_name'])->toBe('Global-Wine')
        ->and($repo->activeForSupplier($supplier->id, ParseMode::Tabular, null)->recipe['mapping']['wine_name'])->toBe('Global-Wine');
});

it('forbids reviewing another company\'s document', function () {
    [$company, $user] = makeTenant();
    $supplier = Supplier::factory()->create(['created_by_company_id' => $company->id]);
    (new ConnectCompanyToSupplierAction)->execute($company->id, $supplier->id);
    $doc = SupplierDocument::factory()->create([
        'supplier_id' => $supplier->id,
        'uploaded_by_company_id' => Company::factory()->create()->id,
        'status' => SupplierDocumentStatus::Analysed->value,
    ]);

    $this->actingAs($user);

    Livewire::test(DocumentReview::class, ['uuid' => $supplier->uuid, 'documentId' => $doc->id])->assertForbidden();
});

it('lets a reviewer edit then approve a single wine, and rejects another', function () {
    [$company, $user] = makeTenant();
    $supplier = Supplier::factory()->create(['created_by_company_id' => $company->id]);
    (new ConnectCompanyToSupplierAction)->execute($company->id, $supplier->id);
    $doc = SupplierDocument::factory()->create([
        'supplier_id' => $supplier->id, 'uploaded_by_company_id' => $company->id,
        'status' => SupplierDocumentStatus::Analysed->value,
    ]);
    $a = ParsedWine::factory()->create(['supplier_document_id' => $doc->id, 'supplier_id' => $supplier->id]);
    $b = ParsedWine::factory()->create(['supplier_document_id' => $doc->id, 'supplier_id' => $supplier->id]);

    $this->actingAs($user);

    Livewire::test(DocumentReview::class, ['uuid' => $supplier->uuid, 'documentId' => $doc->id])
        ->call('startEdit', $a->id)
        ->set('edit.wine_name', 'Corrected Wine')
        ->set('edit.unit_price', '22.50')
        ->call('saveEdit')
        ->call('approve', $a->id)
        ->call('reject', $b->id);

    expect(Product::where('wine_name', 'Corrected Wine')->where('supplier_id', $supplier->id)->exists())->toBeTrue();
    expect($a->fresh()->status)->toBe(ParsedWineStatus::Approved)
        ->and($b->fresh()->status)->toBe(ParsedWineStatus::Rejected);
});

it('refines the recipe with approved examples', function () {
    [$company, $user] = makeTenant();
    $supplier = Supplier::factory()->create(['created_by_company_id' => $company->id]);
    (new ConnectCompanyToSupplierAction)->execute($company->id, $supplier->id);
    $doc = SupplierDocument::factory()->create([
        'supplier_id' => $supplier->id, 'uploaded_by_company_id' => $company->id,
        'file_name' => 'list.pdf', 'status' => SupplierDocumentStatus::Analysed->value,
    ]);
    // The buyer's own (company-scoped) profile — saveRecipe refines this scope only.
    SupplierParseProfile::factory()->create(['supplier_id' => $supplier->id, 'company_id' => $company->id, 'mode' => ParseMode::Document->value, 'recipe' => ['structure' => 's']]);
    ParsedWine::factory()->create([
        'supplier_document_id' => $doc->id, 'supplier_id' => $supplier->id,
        'status' => ParsedWineStatus::Approved->value, 'payload' => ['wine_name' => 'Good Wine', 'unit_price' => '10.00'],
    ]);

    $this->actingAs($user);

    Livewire::test(DocumentReview::class, ['uuid' => $supplier->uuid, 'documentId' => $doc->id])->call('saveRecipe');

    $recipe = SupplierParseProfile::where('supplier_id', $supplier->id)->where('is_active', true)->first()->recipe;
    expect($recipe['examples'][0]['wine_name'])->toBe('Good Wine');
});
