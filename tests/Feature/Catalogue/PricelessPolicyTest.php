<?php

declare(strict_types=1);

use Domain\Catalogue\Models\Product;
use Domain\Supplier\Actions\ApproveAllForDocumentAction;
use Domain\Supplier\Enums\ParsedWineStatus;
use Domain\Supplier\Models\ParsedWine;
use Domain\Supplier\Models\Supplier;
use Domain\Supplier\Models\SupplierDocument;

function proposeWine(int $supplierId, int $documentId, string $name, ?string $price): void
{
    ParsedWine::create([
        'supplier_id' => $supplierId,
        'supplier_document_id' => $documentId,
        'status' => ParsedWineStatus::Proposed->value,
        'confidence' => 0.9,
        'flag' => null,
        'payload' => [
            'id' => null, 'uuid' => null, 'supplier_id' => $supplierId,
            'wine_name' => $name, 'vintage' => 2021, 'format_ml' => 750, 'case_size' => 6,
            'unit_price' => $price, 'stock' => 0,
        ],
    ]);
}

it('never bulk-commits a price-less wine, even unflagged', function () {
    $supplier = Supplier::factory()->create();
    $document = SupplierDocument::factory()->create(['supplier_id' => $supplier->id]);

    proposeWine($supplier->id, $document->id, 'Priced Chablis', '14.50');
    proposeWine($supplier->id, $document->id, 'Free Chablis', null);
    proposeWine($supplier->id, $document->id, 'Zero Chablis', '0.00');

    $committed = (new ApproveAllForDocumentAction)->execute($document->id);

    expect($committed)->toBe(1)
        ->and(Product::where('supplier_id', $supplier->id)->pluck('wine_name')->all())
        ->toBe(['Priced Chablis']);
});

it('archives price-less wines and leaves priced ones, reporting per supplier', function () {
    $supplier = Supplier::factory()->create(['name' => 'Unpriced Cellars']);
    Product::factory()->count(3)->create(['supplier_id' => $supplier->id, 'unit_price' => null]);
    Product::factory()->create(['supplier_id' => $supplier->id, 'unit_price' => '20.00']);

    $this->artisan('wine:archive-priceless')
        ->expectsOutputToContain('Unpriced Cellars: 3')
        ->assertExitCode(0);

    expect(Product::whereNull('archived_at')->where('supplier_id', $supplier->id)->count())->toBe(1)
        ->and(Product::whereNotNull('archived_at')->where('supplier_id', $supplier->id)->count())->toBe(3);
});

it('is a no-op on a clean catalogue', function () {
    $supplier = Supplier::factory()->create();
    Product::factory()->create(['supplier_id' => $supplier->id, 'unit_price' => '20.00']);

    $this->artisan('wine:archive-priceless')
        ->expectsOutputToContain('catalogue is clean')
        ->assertExitCode(0);
});
