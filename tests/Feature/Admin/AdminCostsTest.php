<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Domain\Admin\Models\Admin;
use Domain\Supplier\Actions\RecordLlmCallAction;
use Domain\Supplier\Data\LlmCallData;
use Domain\Supplier\Models\LlmCall;
use Domain\Supplier\Models\Supplier;
use Domain\Supplier\Models\SupplierDocument;
use Domain\Supplier\Repositories\LlmCallRepository;
use Domain\Supplier\Services\ClaudeClient;

it('redirects guests from the admin costs page to the admin login', function () {
    $this->get(route('admin.costs'))->assertRedirect(route('admin.login'));
});

it('shows the cost ledger to an admin with supplier and document context', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');

    $supplier = Supplier::factory()->create(['name' => 'Ledger Cellars']);
    $document = SupplierDocument::factory()->create([
        'supplier_id' => $supplier->id,
        'file_name' => 'ledger-list.pdf',
    ]);

    LlmCall::factory()->create([
        'purpose' => 'extract_wines',
        'supplier_id' => $supplier->id,
        'supplier_document_id' => $document->id,
    ]);

    $this->get(route('admin.costs'))
        ->assertOk()
        ->assertSee('Ledger Cellars')
        ->assertSee('ledger-list.pdf')
        ->assertSee('extract wines');
});

it('records a ledger row through the action', function () {
    $data = (new RecordLlmCallAction)->execute(LlmCallData::from([
        'id' => null,
        'uuid' => null,
        'purpose' => 'derive_mapping',
        'model' => 'claude-haiku-4-5-20251001',
        'input_tokens' => 1000,
        'output_tokens' => 500,
        'cost_usd' => '0.003500',
    ]));

    expect($data->id)->not->toBeNull()
        ->and(LlmCall::sole()->purpose)->toBe('derive_mapping');
});

it('sums totals overall and within a window, and groups by model', function () {
    LlmCall::factory()->create([
        'model' => 'claude-haiku-4-5-20251001',
        'input_tokens' => 1000, 'output_tokens' => 500, 'cost_usd' => '0.0035',
        'created_at' => now()->subDays(40),
    ]);
    LlmCall::factory()->create([
        'model' => 'claude-opus-4-8',
        'input_tokens' => 2000, 'output_tokens' => 1000, 'cost_usd' => '0.035',
        'created_at' => now()->subDay(),
    ]);

    $repo = new LlmCallRepository;

    $all = $repo->totals();
    expect($all['calls'])->toBe(2)
        ->and($all['input_tokens'])->toBe(3000)
        ->and(round($all['cost_usd'], 4))->toBe(0.0385);

    $recent = $repo->totals(CarbonImmutable::now()->subDays(30));
    expect($recent['calls'])->toBe(1)
        ->and(round($recent['cost_usd'], 4))->toBe(0.035);

    expect($repo->byModel()->pluck('model')->all())
        ->toBe(['claude-opus-4-8', 'claude-haiku-4-5-20251001']);
});

it('prices dated model ids at their family rates, not the opus fallback', function () {
    expect(ClaudeClient::priceFor('claude-haiku-4-5-20251001'))->toBe([1.0, 5.0])
        ->and(ClaudeClient::priceFor('claude-sonnet-4-6'))->toBe([3.0, 15.0])
        ->and(ClaudeClient::priceFor('claude-opus-4-8'))->toBe([5.0, 25.0])
        // Unknown models fail safe to the most expensive rate.
        ->and(ClaudeClient::priceFor('something-unknown'))->toBe([5.0, 25.0]);
});
