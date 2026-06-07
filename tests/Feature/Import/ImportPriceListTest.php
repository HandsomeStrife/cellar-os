<?php

declare(strict_types=1);

use App\Livewire\Import\Index;
use Domain\Billing\Enums\Plan;
use Domain\Catalogue\Enums\WineColour;
use Domain\Catalogue\Models\Product;
use Domain\Import\Services\PriceListParser;
use Domain\Supplier\Models\Supplier;
use Domain\User\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    $this->user = User::factory()->create(['plan' => Plan::Starter->value]);
    $this->supplier = Supplier::factory()->create();
    $this->actingAs($this->user);
});

it('parses a CSV into headers and associative rows', function () {
    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, "Name,Colour,Price\nTest Red,Red,30.00\nTest White,White,25.00\n");

    $parsed = (new PriceListParser)->parse($path, 'csv');

    expect($parsed['headers'])->toBe(['Name', 'Colour', 'Price'])
        ->and($parsed['rows'])->toHaveCount(2)
        ->and($parsed['rows'][0])->toBe(['Name' => 'Test Red', 'Colour' => 'Red', 'Price' => '30.00']);

    unlink($path);
});

it('renders the import page', function () {
    $this->get(route('import'))->assertOk()->assertSeeLivewire(Index::class);
});

it('shows an upgrade gate for free users', function () {
    $this->actingAs(User::factory()->create(['plan' => Plan::Free->value]));

    Livewire::test(Index::class)->assertSee('paid feature');
});

it('imports a CSV price list end to end', function () {
    $csv = "Wine,Producer,Country,Colour,Vintage,Price\n"
        ."Chablis Premier Cru,Laroche,France,White,2021,28.50\n"
        ."Barolo Riserva,Conterno,Italy,Red,2017,92.00\n";

    Livewire::test(Index::class)
        ->set('supplierId', $this->supplier->id)
        ->set('upload', UploadedFile::fake()->createWithContent('list.csv', $csv))
        ->call('uploadFile')
        ->assertHasNoErrors()
        ->assertSet('step', 2)
        ->call('toPreview')
        ->assertSet('step', 3)
        ->call('runImport')
        ->assertSet('step', 4)
        ->assertSet('importedCount', 2);

    expect(Product::count())->toBe(2);
    $this->assertDatabaseHas('products', ['wine_name' => 'Chablis Premier Cru', 'supplier_id' => $this->supplier->id]);
    $this->assertDatabaseHas('raw_uploads', ['status' => 'imported']);
    expect($this->supplier->fresh()->column_mapping)->not->toBeNull();

    $barolo = Product::where('wine_name', 'Barolo Riserva')->first();
    expect($barolo->colour)->toBe(WineColour::Red)
        ->and($barolo->vintage)->toBe(2017);
});

it('forbids uploading for free users', function () {
    $this->actingAs(User::factory()->create(['plan' => Plan::Free->value]));

    Livewire::test(Index::class)
        ->set('supplierId', $this->supplier->id)
        ->set('upload', UploadedFile::fake()->createWithContent('list.csv', "Wine\nX\n"))
        ->call('uploadFile')
        ->assertForbidden();

    expect(Product::count())->toBe(0);
});

it('parses an xlsx file', function () {
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([['Name', 'Price'], ['Test Red', '30.00'], ['Test White', '25.00']]);
    $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    $parsed = (new PriceListParser)->parse($path, 'xlsx');

    expect($parsed['headers'])->toBe(['Name', 'Price'])
        ->and($parsed['rows'])->toHaveCount(2)
        ->and($parsed['rows'][0]['Name'])->toBe('Test Red');

    unlink($path);
});

it('handles a malformed upload gracefully', function () {
    Livewire::test(Index::class)
        ->set('supplierId', $this->supplier->id)
        ->set('upload', UploadedFile::fake()->createWithContent('broken.xlsx', 'this is not a real spreadsheet'))
        ->call('uploadFile')
        ->assertHasErrors('upload')
        ->assertSet('step', 1);
});

it('does not duplicate products when the same list is re-imported', function () {
    $csv = "Wine,Vintage,Price\nChablis,2021,28.50\n";

    $import = function () use ($csv) {
        Livewire::test(Index::class)
            ->set('supplierId', $this->supplier->id)
            ->set('upload', UploadedFile::fake()->createWithContent('list.csv', $csv))
            ->call('uploadFile')
            ->call('toPreview')
            ->call('runImport')
            ->assertSet('step', 4);
    };

    $import();
    $import();

    expect(Product::where('wine_name', 'Chablis')->count())->toBe(1);
});

it('forbids importing another user\'s upload', function () {
    // Owner uploads but does not import.
    $component = Livewire::test(Index::class)
        ->set('supplierId', $this->supplier->id)
        ->set('upload', UploadedFile::fake()->createWithContent('list.csv', "Wine\nChablis\n"))
        ->call('uploadFile');
    $rawUploadId = $component->get('rawUploadId');

    // A different user tries to import it.
    $this->actingAs(User::factory()->create(['plan' => Plan::Starter->value]));
    Livewire::test(Index::class)
        ->set('rawUploadId', $rawUploadId)
        ->set('mapping', ['wine_name' => 'Wine'])
        ->set('headers', ['Wine'])
        ->call('runImport')
        ->assertForbidden();

    expect(Product::count())->toBe(0);
});

it('requires the wine name column to be mapped', function () {
    Livewire::test(Index::class)
        ->set('supplierId', $this->supplier->id)
        ->set('upload', UploadedFile::fake()->createWithContent('list.csv', "Foo,Bar\n1,2\n"))
        ->call('uploadFile')
        ->set('mapping.wine_name', '')
        ->call('toPreview')
        ->assertHasErrors('mapping.wine_name')
        ->assertSet('step', 2);
});
