<?php

declare(strict_types=1);

use App\Livewire\Admin\SupplierShow;
use App\Livewire\Suppliers\DocumentReview;
use Domain\Admin\Models\Admin;
use Domain\Catalogue\Models\Product;
use Domain\Company\Models\Company;
use Domain\Supplier\Actions\ConnectCompanyToSupplierAction;
use Domain\Supplier\Enums\ParsedWineStatus;
use Domain\Supplier\Enums\SupplierDocumentStatus;
use Domain\Supplier\Jobs\ApproveAllForDocumentJob;
use Domain\Supplier\Models\ParsedWine;
use Domain\Supplier\Models\Supplier;
use Domain\Supplier\Models\SupplierDocument;
use Domain\Supplier\Support\BulkApprovalProgress;
use Domain\User\Models\User;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;

function makeApprovalFixture(): array
{
    $supplier = Supplier::factory()->create();
    $document = SupplierDocument::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => SupplierDocumentStatus::Analysed->value,
    ]);

    return [$supplier, $document];
}

it('approves a document\'s proposed wines and reports progress via the job', function () {
    [$supplier, $document] = makeApprovalFixture();
    ParsedWine::factory()->count(3)->create(['supplier_document_id' => $document->id, 'supplier_id' => $supplier->id]);
    ParsedWine::factory()->create([
        'supplier_document_id' => $document->id, 'supplier_id' => $supplier->id, 'flag' => 'suspicious_price',
    ]);

    (new ApproveAllForDocumentJob($document->id, skipFlagged: true))->handle();

    expect(Product::where('supplier_id', $supplier->id)->count())->toBe(3)
        ->and(ParsedWine::where('status', ParsedWineStatus::Approved->value)->count())->toBe(3)
        // The flagged row is untouched.
        ->and(ParsedWine::whereNotNull('flag')->where('status', ParsedWineStatus::Proposed->value)->count())->toBe(1);

    $progress = BulkApprovalProgress::get($document->id);
    expect($progress['state'])->toBe('done')
        ->and($progress['approved'])->toBe(3)
        ->and($progress['total'])->toBe(3);
});

it('records a catalogue commit note when asked to', function () {
    [$supplier, $document] = makeApprovalFixture();
    ParsedWine::factory()->count(2)->create(['supplier_document_id' => $document->id, 'supplier_id' => $supplier->id]);

    (new ApproveAllForDocumentJob($document->id, skipFlagged: true, recordCommitNote: true))->handle();

    $this->assertDatabaseHas('supplier_notes', ['supplier_id' => $supplier->id]);
});

it('clears the progress marker when the document has been deleted', function () {
    BulkApprovalProgress::queued(999999);

    (new ApproveAllForDocumentJob(999999))->handle();

    expect(BulkApprovalProgress::get(999999))->toBeNull();
});

it('queues the bulk approval from the review screen and refuses a duplicate', function () {
    Bus::fake();

    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $supplier = Supplier::factory()->create(['created_by_company_id' => $company->id]);
    (new ConnectCompanyToSupplierAction)->execute($company->id, $supplier->id);
    $document = SupplierDocument::factory()->create([
        'supplier_id' => $supplier->id,
        'uploaded_by_company_id' => $company->id,
        'status' => SupplierDocumentStatus::Analysed->value,
    ]);

    $this->actingAs($user);

    Livewire::test(DocumentReview::class, ['uuid' => $supplier->uuid, 'documentId' => $document->id])
        ->call('approveAll');

    Bus::assertDispatchedTimes(ApproveAllForDocumentJob::class, 1);
    expect(BulkApprovalProgress::isActive($document->id))->toBeTrue();

    // A second click while queued/running must not dispatch again.
    Livewire::test(DocumentReview::class, ['uuid' => $supplier->uuid, 'documentId' => $document->id])
        ->call('approveAll');

    Bus::assertDispatchedTimes(ApproveAllForDocumentJob::class, 1);

    BulkApprovalProgress::clear($document->id);
});

it('queues the admin bulk approval with flag-skipping and a commit note', function () {
    Bus::fake();
    $this->actingAs(Admin::factory()->create(), 'admin');
    [$supplier, $document] = makeApprovalFixture();

    Livewire::test(SupplierShow::class, ['uuid' => $supplier->uuid])
        ->call('approveDocument', $document->id);

    Bus::assertDispatched(ApproveAllForDocumentJob::class, fn ($job) => $job->documentId === $document->id
        && $job->skipFlagged === true
        && $job->recordCommitNote === true);
    expect(BulkApprovalProgress::isActive($document->id))->toBeTrue();

    BulkApprovalProgress::clear($document->id);
});

it('marks progress failed when the job dies', function () {
    [$supplier, $document] = makeApprovalFixture();

    $job = new ApproveAllForDocumentJob($document->id);
    $job->failed(new RuntimeException('worker timeout'));

    $progress = BulkApprovalProgress::get($document->id);
    expect($progress['state'])->toBe('failed')
        ->and($progress['message'])->toBe('worker timeout');
});
