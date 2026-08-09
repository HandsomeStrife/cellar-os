<?php

declare(strict_types=1);

use App\Livewire\Catalogue\Index;
use Domain\Catalogue\Enums\PriceState;
use Domain\Catalogue\Models\Product;
use Domain\Catalogue\Repositories\ProductRepository;
use Domain\Import\Services\NormaliseService;
use Domain\Order\Models\Order;
use Domain\Supplier\Actions\ApproveAllForDocumentAction;
use Domain\Supplier\Actions\ConnectCompanyToSupplierAction;
use Domain\Supplier\Enums\ParsedWineStatus;
use Domain\Supplier\Models\ParsedWine;
use Domain\Supplier\Models\Supplier;
use Domain\Supplier\Models\SupplierDocument;
use Domain\User\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->supplier = Supplier::factory()->create(['name' => 'Les Caves']);
    (new ConnectCompanyToSupplierAction)->execute($this->user->company_id, $this->supplier->id);
});

it('reads a supplier\'s own POA wording out of the price cell', function () {
    expect(PriceState::fromPriceText('POA'))->toBe(PriceState::Poa)
        ->and(PriceState::fromPriceText('Price on application'))->toBe(PriceState::Poa)
        ->and(PriceState::fromPriceText('please ask your rep about availability'))->toBe(PriceState::Poa)
        ->and(PriceState::fromPriceText('TBC'))->toBe(PriceState::Tbc)
        ->and(PriceState::fromPriceText('24.50'))->toBeNull()
        ->and(PriceState::fromPriceText(''))->toBeNull()
        // Word-boundary matched, so a wine name can't be mistaken for a quote.
        ->and(PriceState::fromPriceText('Poachers Rest'))->toBeNull();
});

it('normalises the real Les Caves allocation wording into a POA wine', function () {
    $product = (new NormaliseService)->toProductData(
        [
            'Wine' => 'Domaine Renaud Bruyère et Adeline Houillon, Pupillin',
            'Price' => 'We receive tiny allocations from this grower so rarely have bottles in stock '
                .'but please ask your rep if you would like more information about availability',
        ],
        ['wine_name' => 'Wine', 'unit_price' => 'Price'],
    );

    expect($product->unit_price)->toBeNull()
        ->and($product->price_state)->toBe(PriceState::Poa)
        // The supplier's own wording is kept — it's the useful part.
        ->and($product->price_note)->toContain('tiny allocations');
});

it('leaves a genuinely blank price as missing data, not POA', function () {
    $product = (new NormaliseService)->toProductData(
        ['Wine' => 'Chablis', 'Price' => ''],
        ['wine_name' => 'Wine', 'unit_price' => 'Price'],
    );

    expect($product->price_state)->toBe(PriceState::Priced)
        ->and($product->unit_price)->toBeNull();
});

it('keeps POA wines out of the price-less archive sweep', function () {
    $poa = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'unit_price' => null,
        'price_state' => PriceState::Poa,
    ]);
    $missing = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'unit_price' => null,
        'price_state' => PriceState::Priced,
    ]);

    $this->artisan('wine:archive-priceless')->assertSuccessful();

    expect($poa->fresh()->archived_at)->toBeNull()
        ->and($missing->fresh()->archived_at)->not->toBeNull();
});

it('bulk-approves a stated POA row but still skips a price-less one', function () {
    $document = SupplierDocument::factory()->create(['supplier_id' => $this->supplier->id]);

    $row = fn (array $overrides) => ParsedWine::factory()->create([
        'supplier_document_id' => $document->id,
        'status' => ParsedWineStatus::Proposed->value,
        'flag' => null,
        'payload' => [
            'wine_name' => $overrides['wine_name'],
            'supplier_id' => $this->supplier->id,
            'unit_price' => null,
            'price_state' => $overrides['price_state'],
            'format_ml' => 750,
            'case_size' => 6,
            'stock' => 0,
        ],
    ]);

    $row(['wine_name' => 'Pupillin Ploussard', 'price_state' => PriceState::Poa->value]);
    $row(['wine_name' => 'Unreadable Row', 'price_state' => PriceState::Priced->value]);

    $approved = (new ApproveAllForDocumentAction)->execute($document->id);

    expect($approved)->toBe(1)
        ->and(Product::where('wine_name', 'Pupillin Ploussard')->exists())->toBeTrue()
        ->and(Product::where('wine_name', 'Unreadable Row')->exists())->toBeFalse();
});

it('sorts POA wines to the end of a price sort in both directions', function () {
    $cheap = Product::factory()->create(['supplier_id' => $this->supplier->id, 'unit_price' => '10.00']);
    $dear = Product::factory()->create(['supplier_id' => $this->supplier->id, 'unit_price' => '90.00']);
    $poa = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'unit_price' => null,
        'price_state' => PriceState::Poa,
    ]);

    $repository = new ProductRepository;

    expect($repository->search(sort: 'unit_price', direction: 'asc')->pluck('id')->all())
        ->toBe([$cheap->id, $dear->id, $poa->id])
        ->and($repository->search(sort: 'unit_price', direction: 'desc')->pluck('id')->all())
        ->toBe([$dear->id, $cheap->id, $poa->id]);
});

it('shows POA in the catalogue instead of a price', function () {
    Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'wine_name' => 'Pupillin Ploussard',
        'unit_price' => null,
        'price_state' => PriceState::Poa,
        'price_note' => 'Tiny allocations — ask your rep.',
    ]);

    Livewire::test(Index::class)
        ->assertSee('POA')
        ->assertSee('Tiny allocations — ask your rep.', escape: false);
});

it('puts a POA wine on a purchase order without a price and excludes it from the total', function () {
    $poa = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'unit_price' => null,
        'price_state' => PriceState::Poa,
    ]);
    $priced = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'unit_price' => '20.00',
    ]);

    Livewire::test(Index::class)
        ->call('addToBasket', $poa->id)
        ->call('addToBasket', $priced->id)
        ->call('createOrders');

    $order = Order::with('items')->firstOrFail();
    $poaLine = $order->items->firstWhere('product_id', $poa->id);

    expect($poaLine->unit_price_at_order)->toBeNull()
        // Only the priced line contributes.
        ->and((float) $order->total)->toBe(20.0);
});
