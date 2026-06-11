<?php

declare(strict_types=1);

use Domain\Catalogue\Models\Lwin;
use Domain\Catalogue\Models\Product;
use Domain\Catalogue\Models\WineFact;
use Domain\Catalogue\Services\LwinMatchService;
use Domain\Supplier\Models\Supplier;
use Domain\Supplier\Services\ClaudeClient;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeClaudeClient;

function lwinFixtureCsv(): string
{
    return implode("\n", [
        'LWIN,STATUS,DISPLAY_NAME,PRODUCER_TITLE,PRODUCER_NAME,WINE,COUNTRY,REGION,SUB_REGION,COLOUR,TYPE,FIRST_VINTAGE,FINAL_VINTAGE,REFERENCE',
        '1011247,Live,"Chateau Margaux, Margaux",Chateau,Margaux,Margaux,France,Bordeaux,Margaux,Red,Still,1900,,',
        '1012316,Live,"Domaine Laroche, Chablis Premier Cru",Domaine,Laroche,Chablis Premier Cru,France,Burgundy,Chablis,White,Still,1990,,',
        '1099999,Live,"Felton Road, Bannockburn Pinot Noir",,Felton Road,Bannockburn Pinot Noir,New Zealand,Central Otago,,Red,Still,1997,,',
        '1088888,Live,"Felton Road, Block 5 Pinot Noir",,Felton Road,Block 5 Pinot Noir,New Zealand,Central Otago,,Red,Still,1997,,',
        'BADROW,Live,Not A Wine,,,,,,,,,,,',
        '',
    ]);
}

it('imports the LWIN reference file with normalised lookup keys', function () {
    Storage::fake('local');
    Storage::disk('local')->put('lwin/lwin-database.csv', lwinFixtureCsv());

    $this->artisan('wine:lwin-refresh')->assertSuccessful();
    // Idempotent re-run.
    $this->artisan('wine:lwin-refresh')->assertSuccessful();

    expect(Lwin::count())->toBe(4); // BADROW (non-7-digit) skipped

    $laroche = Lwin::firstWhere('lwin', '1012316');
    expect($laroche->producer_name)->toBe('Laroche')
        ->and($laroche->region)->toBe('Burgundy')
        ->and($laroche->identity_key)->toBe('laroche|chablis premier cru')
        ->and($laroche->name_key)->toBe('domaine laroche chablis premier cru');
});

it('matches products and facts deterministically by identity and display name', function () {
    Storage::fake('local');
    Storage::disk('local')->put('lwin/lwin-database.csv', lwinFixtureCsv());
    $this->artisan('wine:lwin-refresh')->assertSuccessful();

    $supplier = Supplier::factory()->create();
    // Identity match: producer + wine align with an LWIN identity key.
    $identity = Product::factory()->create(['supplier_id' => $supplier->id, 'producer' => 'Laroche', 'wine_name' => 'Chablis Premier Cru']);
    // Display-name match (producer embedded in the name, Farr-style; no producer column).
    $byName = Product::factory()->create(['supplier_id' => $supplier->id, 'producer' => null, 'wine_name' => 'Domaine Laroche, Chablis Premier Cru']);
    // No match.
    $stranger = Product::factory()->create(['supplier_id' => $supplier->id, 'producer' => 'Nobody', 'wine_name' => 'Mystery Cuvee']);

    $fact = WineFact::factory()->create(['identity_key' => 'laroche|chablis premier cru', 'producer' => 'Laroche', 'wine_name' => 'Chablis Premier Cru']);

    app(LwinMatchService::class)->match();

    expect($identity->fresh()->lwin)->toBe('1012316')
        ->and($identity->fresh()->lwin_source)->toBe('identity')
        ->and($byName->fresh()->lwin)->toBe('1012316')
        ->and($byName->fresh()->lwin_source)->toBe('name')
        ->and($stranger->fresh()->lwin)->toBeNull()
        ->and($fact->fresh()->lwin)->toBe('1012316');
});

it('never guesses between ambiguous LWINs deterministically, but the model can pick or abstain', function () {
    Storage::fake('local');
    Storage::disk('local')->put('lwin/lwin-database.csv', lwinFixtureCsv());
    $this->artisan('wine:lwin-refresh')->assertSuccessful();

    $supplier = Supplier::factory()->create();
    // Felton Road has TWO pinots — "Pinot Noir" alone must not match deterministically.
    $ambiguous = Product::factory()->create(['supplier_id' => $supplier->id, 'producer' => 'Felton Road', 'wine_name' => 'Bannockburn Pinot']);

    $fake = new FakeClaudeClient;
    $fake->lwinPicks = ['0' => '1099999']; // the model resolves it
    app()->instance(ClaudeClient::class, $fake);

    $service = new LwinMatchService(app(ClaudeClient::class));

    // Deterministic only: stays unmatched.
    $service->match();
    expect($ambiguous->fresh()->lwin)->toBeNull();

    // With the LLM pass: matched via the model's pick (validated against candidates).
    $service->match(withLlm: true);
    expect($ambiguous->fresh()->lwin)->toBe('1099999')
        ->and($ambiguous->fresh()->lwin_source)->toBe('llm');
});

it('rejects model picks that are not in the candidate list', function () {
    Storage::fake('local');
    Storage::disk('local')->put('lwin/lwin-database.csv', lwinFixtureCsv());
    $this->artisan('wine:lwin-refresh')->assertSuccessful();

    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create(['supplier_id' => $supplier->id, 'producer' => 'Felton Road', 'wine_name' => 'Some Unknown Cuvee']);

    $fake = new FakeClaudeClient;
    $fake->lwinPicks = ['0' => '1011247']; // hallucinated: a Margaux LWIN, not a Felton Road candidate

    (new LwinMatchService($fake))->match(withLlm: true);

    expect($product->fresh()->lwin)->toBeNull(); // invalid pick discarded
});
