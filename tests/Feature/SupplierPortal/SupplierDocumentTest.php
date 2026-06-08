<?php

declare(strict_types=1);

use App\Livewire\SupplierPortal\Dashboard;
use App\Livewire\SupplierPortal\Documents;
use Domain\Company\Models\Company;
use Domain\Supplier\Actions\DeleteSupplierAction;
use Domain\Supplier\Enums\SupplierDocumentStatus;
use Domain\Supplier\Models\Supplier;
use Domain\Supplier\Models\SupplierDocument;
use Domain\Supplier\Models\SupplierUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('never surfaces a buyer\'s private document to the supplier portal', function () {
    $supplierUser = SupplierUser::factory()->create();
    // A buyer's private document about this supplier.
    SupplierDocument::factory()->create([
        'supplier_id' => $supplierUser->supplier_id,
        'uploaded_by_company_id' => Company::factory()->create()->id,
        'title' => 'Buyer Private Note',
    ]);

    $this->actingAs($supplierUser, 'supplier');

    Livewire::test(Documents::class)->assertDontSee('Buyer Private Note');
});

it('does not count or list buyer documents on the supplier dashboard', function () {
    $supplierUser = SupplierUser::factory()->create();
    SupplierDocument::factory()->create([
        'supplier_id' => $supplierUser->supplier_id,
        'uploaded_by_company_id' => Company::factory()->create()->id,
        'title' => 'Buyer Dashboard Note',
    ]);

    $this->actingAs($supplierUser, 'supplier');

    Livewire::test(Dashboard::class)
        ->assertDontSee('Buyer Dashboard Note')
        ->assertViewHas('documents', fn ($docs) => $docs->isEmpty());
});

it('forbids the supplier portal downloading or deleting a buyer document', function () {
    Storage::fake('local');
    $supplierUser = SupplierUser::factory()->create();
    $document = SupplierDocument::factory()->create([
        'supplier_id' => $supplierUser->supplier_id,
        'uploaded_by_company_id' => Company::factory()->create()->id,
        'storage_path' => 'supplier-documents/buyer.csv',
    ]);
    Storage::disk('local')->put($document->storage_path, 'data');

    $this->actingAs($supplierUser, 'supplier');

    Livewire::test(Documents::class)->call('delete', $document->id)->assertForbidden();
    $this->get(route('supplier.documents.download', $document->id))->assertForbidden();
    $this->assertDatabaseHas('supplier_documents', ['id' => $document->id]);
});

it('deletes backing files when a supplier is deleted', function () {
    Storage::fake('local');
    $supplier = Supplier::factory()->create();
    $document = SupplierDocument::factory()->create([
        'supplier_id' => $supplier->id,
        'storage_path' => 'supplier-documents/portfolio.csv',
    ]);
    Storage::disk('local')->put($document->storage_path, 'data');

    (new DeleteSupplierAction)->execute($supplier->id);

    Storage::disk('local')->assertMissing('supplier-documents/portfolio.csv');
    $this->assertDatabaseMissing('supplier_documents', ['id' => $document->id]);
});

it('uploads a portfolio and records it awaiting analysis', function () {
    Storage::fake('local');
    $user = SupplierUser::factory()->create();
    $this->actingAs($user, 'supplier');

    Livewire::test(Documents::class)
        ->set('title', 'Spring portfolio')
        ->set('upload', UploadedFile::fake()->create('portfolio.csv', 50, 'text/csv'))
        ->call('upload')
        ->assertHasNoErrors();

    $document = SupplierDocument::where('supplier_id', $user->supplier_id)->first();

    expect($document)->not->toBeNull()
        ->and($document->status)->toBe(SupplierDocumentStatus::AwaitingAnalysis)
        ->and($document->uploaded_by_supplier_user_id)->toBe($user->id);

    Storage::disk('local')->assertExists($document->storage_path);
});

it('only shows the signed-in supplier its own documents', function () {
    $mine = SupplierUser::factory()->create();
    $theirs = Supplier::factory()->create();
    SupplierDocument::factory()->create(['supplier_id' => $mine->supplier_id, 'title' => 'My sheet']);
    SupplierDocument::factory()->create(['supplier_id' => $theirs->id, 'title' => 'Their sheet']);

    $this->actingAs($mine, 'supplier');

    Livewire::test(Documents::class)
        ->assertSee('My sheet')
        ->assertDontSee('Their sheet');
});

it('forbids downloading another supplier\'s document', function () {
    Storage::fake('local');
    $mine = SupplierUser::factory()->create();
    $otherSupplier = Supplier::factory()->create();
    $document = SupplierDocument::factory()->create([
        'supplier_id' => $otherSupplier->id,
        'storage_path' => 'supplier-documents/secret.csv',
    ]);
    Storage::disk('local')->put($document->storage_path, 'data');

    $this->actingAs($mine, 'supplier')
        ->get(route('supplier.documents.download', $document->id))
        ->assertForbidden();
});

it('lets a supplier delete its own document and removes the file', function () {
    Storage::fake('local');
    $user = SupplierUser::factory()->create();
    $document = SupplierDocument::factory()->create([
        'supplier_id' => $user->supplier_id,
        'storage_path' => 'supplier-documents/mine.csv',
    ]);
    Storage::disk('local')->put($document->storage_path, 'data');

    $this->actingAs($user, 'supplier');

    Livewire::test(Documents::class)->call('delete', $document->id);

    $this->assertDatabaseMissing('supplier_documents', ['id' => $document->id]);
    Storage::disk('local')->assertMissing('supplier-documents/mine.csv');
});

it('forbids deleting another supplier\'s document', function () {
    $user = SupplierUser::factory()->create();
    $otherSupplier = Supplier::factory()->create();
    $document = SupplierDocument::factory()->create(['supplier_id' => $otherSupplier->id]);

    $this->actingAs($user, 'supplier');

    Livewire::test(Documents::class)->call('delete', $document->id)->assertForbidden();

    $this->assertDatabaseHas('supplier_documents', ['id' => $document->id]);
});
