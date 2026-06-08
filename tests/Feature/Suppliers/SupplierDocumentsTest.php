<?php

declare(strict_types=1);

use App\Livewire\Suppliers\Documents;
use Domain\Company\Models\Company;
use Domain\Supplier\Actions\ConnectCompanyToSupplierAction;
use Domain\Supplier\Enums\SupplierDocumentStatus;
use Domain\Supplier\Jobs\AnalyseSupplierDocumentJob;
use Domain\Supplier\Models\Supplier;
use Domain\Supplier\Models\SupplierDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    [$this->company, $this->user, $this->venue] = makeTenant();
    $this->supplier = Supplier::factory()->create(['created_by_company_id' => $this->company->id]);
    (new ConnectCompanyToSupplierAction)->execute($this->company->id, $this->supplier->id);
    $this->actingAs($this->user);
});

it('uploads a document for a connected supplier and records it awaiting analysis', function () {
    Storage::fake('local');

    Livewire::test(Documents::class, ['uuid' => $this->supplier->uuid])
        ->set('docTitle', 'Spring list')
        ->set('upload', UploadedFile::fake()->create('list.csv', 40, 'text/csv'))
        ->call('upload')
        ->assertHasNoErrors();

    $document = SupplierDocument::where('supplier_id', $this->supplier->id)->first();
    expect($document)->not->toBeNull()
        ->and($document->status)->toBe(SupplierDocumentStatus::AwaitingAnalysis)
        ->and($document->uploaded_by_company_id)->toBe($this->company->id)
        ->and($document->uploaded_by_user_id)->toBe($this->user->id);

    Storage::disk('local')->assertExists($document->storage_path);
});

it('forbids opening documents for a supplier you are not connected to', function () {
    $other = Supplier::factory()->create();

    Livewire::test(Documents::class, ['uuid' => $other->uuid])->assertForbidden();
});

it('only lists your own company\'s documents for the supplier', function () {
    SupplierDocument::factory()->create([
        'supplier_id' => $this->supplier->id,
        'uploaded_by_company_id' => $this->company->id,
        'title' => 'Mine',
    ]);
    SupplierDocument::factory()->create([
        'supplier_id' => $this->supplier->id,
        'uploaded_by_company_id' => Company::factory()->create()->id,
        'title' => 'Theirs',
    ]);

    Livewire::test(Documents::class, ['uuid' => $this->supplier->uuid])
        ->assertSee('Mine')
        ->assertDontSee('Theirs');
});

it('queues analysis for its own document', function () {
    Bus::fake();
    $document = SupplierDocument::factory()->create([
        'supplier_id' => $this->supplier->id,
        'uploaded_by_company_id' => $this->company->id,
    ]);

    Livewire::test(Documents::class, ['uuid' => $this->supplier->uuid])->call('analyse', $document->id);

    Bus::assertDispatched(AnalyseSupplierDocumentJob::class, fn ($job) => $job->documentId === $document->id);
});

it('forbids analysing/deleting another company\'s document', function () {
    $document = SupplierDocument::factory()->create([
        'supplier_id' => $this->supplier->id,
        'uploaded_by_company_id' => Company::factory()->create()->id,
    ]);

    Livewire::test(Documents::class, ['uuid' => $this->supplier->uuid])->call('delete', $document->id)->assertForbidden();
    $this->assertDatabaseHas('supplier_documents', ['id' => $document->id]);
});

it('forbids downloading another company\'s document', function () {
    Storage::fake('local');
    $document = SupplierDocument::factory()->create([
        'supplier_id' => $this->supplier->id,
        'uploaded_by_company_id' => Company::factory()->create()->id,
        'storage_path' => 'supplier-documents/theirs.csv',
    ]);
    Storage::disk('local')->put($document->storage_path, 'data');

    $this->get(route('suppliers.documents.download', $document->id))->assertForbidden();
});
