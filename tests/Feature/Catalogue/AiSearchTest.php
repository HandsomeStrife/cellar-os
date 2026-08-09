<?php

declare(strict_types=1);

use App\Livewire\Catalogue\Index;
use Domain\Catalogue\Data\AiSearchFilterData;
use Domain\Catalogue\Enums\WineSubType;
use Domain\Catalogue\Enums\WineType;
use Domain\Catalogue\Models\Product;
use Domain\Catalogue\Services\AiSearchService;
use Domain\Supplier\Actions\ConnectCompanyToSupplierAction;
use Domain\Supplier\Models\Supplier;
use Domain\Supplier\Services\ClaudeClient;
use Domain\User\Models\User;
use Livewire\Livewire;

/** A ClaudeClient that answers with a canned interpretation — no live API. */
class FakeSearchClient extends ClaudeClient
{
    public int $calls = 0;

    public function __construct(public array $answer = [], public bool $explode = false)
    {
        // Skip parent config lookup; this client never talks to the API.
    }

    public function interpretSearch(string $query, array $facets, ?string $model = null): array
    {
        $this->calls++;

        if ($this->explode) {
            throw new RuntimeException('API unavailable');
        }

        return $this->answer;
    }
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->supplier = Supplier::factory()->create();
    (new ConnectCompanyToSupplierAction)->execute($this->user->company_id, $this->supplier->id);

    $this->answer = [
        'summary' => 'Looking for Rhône reds under £20 a bottle.',
        'search' => '',
        'type' => 'Red',
        'sub_type' => '',
        'country' => 'France',
        'region' => 'Rhône',
        'producer' => '',
        'grape' => '',
        'price_min' => '',
        'price_max' => '20',
        'vintage_min' => '',
        'vintage_max' => '',
    ];
});

it('reads a plain-English question into catalogue filters', function () {
    $filter = AiSearchFilterData::fromModelOutput($this->answer);

    expect($filter->type)->toBe(WineType::Red)
        ->and($filter->country)->toBe('France')
        ->and($filter->region)->toBe('Rhône')
        ->and($filter->price_max)->toBe(20.0)
        ->and($filter->price_min)->toBeNull()
        ->and($filter->summary)->toContain('Rhône');
});

it('drops a value it cannot honour rather than guessing', function () {
    $filter = AiSearchFilterData::fromModelOutput([
        ...$this->answer,
        'type' => 'Chartreuse',          // not a wine type
        'price_max' => 'about twenty',   // not a number
        'vintage_min' => '',
    ]);

    expect($filter->type)->toBeNull()
        ->and($filter->price_max)->toBeNull();
});

it('discards a sub-type that contradicts the type', function () {
    $filter = AiSearchFilterData::fromModelOutput([
        ...$this->answer,
        'type' => WineType::Red->value,
        'sub_type' => WineSubType::SparklingRose->value,
    ]);

    expect($filter->type)->toBe(WineType::Red)
        ->and($filter->sub_type)->toBeNull();
});

it('fills the ordinary filters so the buyer can see and correct them', function () {
    $this->app->bind(AiSearchService::class, fn () => new AiSearchService(new FakeSearchClient($this->answer)));

    Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'wine_name' => 'Crozes-Hermitage',
        'colour' => WineType::Red,
        'country' => 'France',
        'region' => 'Rhône',
        'unit_price' => '18.00',
    ]);

    Livewire::test(Index::class)
        ->set('aiQuery', 'rhone reds under twenty quid')
        ->call('runAiSearch')
        // The interpretation lands in the ordinary, editable filters…
        ->assertSet('colour', 'Red')
        ->assertSet('country', 'France')
        ->assertSet('region', 'Rhône')
        ->assertSet('priceMax', '20')
        // …with what it understood, and why each wine is here.
        ->assertSee('Looking for Rhône reds')
        ->assertSee('Crozes-Hermitage')
        ->assertSee('within budget');
});

it('searches as plain text when the model cannot be reached', function () {
    $this->app->bind(AiSearchService::class, fn () => new AiSearchService(new FakeSearchClient(explode: true)));

    Livewire::test(Index::class)
        ->set('aiQuery', 'something lovely')
        ->call('runAiSearch')
        ->assertSet('search', 'something lovely')
        ->assertSet('aiFailed', true)
        ->assertSee('searched for it as plain text');
});

it('stops a company hammering the model', function () {
    $this->app->bind(AiSearchService::class, fn () => new AiSearchService(new FakeSearchClient($this->answer)));

    $component = Livewire::test(Index::class);

    // Twenty questions a minute is generous; the twenty-first is refused.
    for ($i = 0; $i < 20; $i++) {
        $component->set('aiQuery', 'query number '.$i)->call('runAiSearch');
    }

    $component
        ->set('colour', '')
        ->set('aiQuery', 'one more please')
        ->call('runAiSearch')
        // Refused, so nothing was interpreted and no filters were applied.
        ->assertSet('colour', '');
});

it('falls back to a plain text search when the model cannot be reached', function () {
    $service = new AiSearchService(new FakeSearchClient(explode: true));

    expect($service->interpret('anything at all', [], 1))->toBeNull();
});

it('answers an identical question from cache instead of calling again', function () {
    $client = new FakeSearchClient($this->answer);
    $service = new AiSearchService($client);

    $service->interpret('rhone reds under twenty', [], 1);
    $service->interpret('Rhone Reds Under Twenty', [], 1);

    expect($client->calls)->toBe(1);
});

it('does not share one company\'s interpretation with another', function () {
    $client = new FakeSearchClient($this->answer);
    $service = new AiSearchService($client);

    $service->interpret('rhone reds', [], 1);
    $service->interpret('rhone reds', [], 2);

    expect($client->calls)->toBe(2);
});

it('explains each wine by the criteria it actually meets', function () {
    $filter = AiSearchFilterData::fromModelOutput($this->answer);

    $match = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'colour' => WineType::Red,
        'country' => 'France',
        'region' => 'Rhône',
        'unit_price' => '18.00',
    ])->getData();

    // Same filter, but this wine only satisfies some of it.
    $partial = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'colour' => WineType::Red,
        'country' => 'Italy',
        'region' => null,
        'unit_price' => '18.00',
    ])->getData();

    $reasons = (new AiSearchService(new FakeSearchClient))->reasons($filter, [$match, $partial]);

    expect($reasons[$match->id])->toContain('Red')
        ->and($reasons[$match->id])->toContain('Rhône')
        ->and($reasons[$match->id])->toContain('within budget')
        // The reason never claims a criterion the wine doesn't meet.
        ->and($reasons[$partial->id])->not->toContain('Rhône')
        ->and($reasons[$partial->id])->not->toContain('France');
});

it('treats a query the model made nothing of as empty', function () {
    $filter = AiSearchFilterData::fromModelOutput([
        'summary' => 'I could not tell what you were after.',
        'search' => '', 'type' => '', 'sub_type' => '', 'country' => '', 'region' => '',
        'producer' => '', 'grape' => '', 'price_min' => '', 'price_max' => '',
        'vintage_min' => '', 'vintage_max' => '',
    ]);

    expect($filter->isEmpty())->toBeTrue();
});

it('gives up the sparsest criteria until the search finds something', function () {
    $filter = AiSearchFilterData::fromModelOutput([
        ...$this->answer,
        'region' => 'Bourgogne',
        'grape' => 'Chardonnay',
        'producer' => 'Some Grower',
    ]);

    // Nothing matches until grape AND producer are set aside.
    $count = fn (AiSearchFilterData $candidate) => $candidate->grape === '' && $candidate->producer === '' ? 12 : 0;

    ['filter' => $relaxed, 'dropped' => $dropped] = (new AiSearchService(new FakeSearchClient))->relax($filter, $count);

    expect($dropped)->toBe(['grape', 'producer'])
        ->and($relaxed->grape)->toBe('')
        ->and($relaxed->producer)->toBe('')
        // What the buyer actually asked for is kept.
        ->and($relaxed->type)->toBe(WineType::Red)
        ->and($relaxed->region)->toBe('Bourgogne');
});

it('leaves a search that already finds something alone', function () {
    $filter = AiSearchFilterData::fromModelOutput([...$this->answer, 'grape' => 'Syrah']);

    ['filter' => $relaxed, 'dropped' => $dropped] = (new AiSearchService(new FakeSearchClient))
        ->relax($filter, fn () => 5);

    expect($dropped)->toBe([])->and($relaxed->grape)->toBe('Syrah');
});

it('tells the buyer what it set aside', function () {
    $this->app->bind(AiSearchService::class, fn () => new AiSearchService(
        new FakeSearchClient([...$this->answer, 'grape' => 'Nebbiolo'])
    ));

    // A catalogue with a Rhône red under £20, but no Nebbiolo in it.
    Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'colour' => WineType::Red,
        'country' => 'France',
        'region' => 'Rhône',
        'grape' => ['Syrah'],
        'unit_price' => '18.00',
    ]);

    Livewire::test(Index::class)
        ->set('aiQuery', 'rhone nebbiolo under twenty')
        ->call('runAiSearch')
        ->assertSet('grape', '')
        ->assertSee('set aside grape');
});
