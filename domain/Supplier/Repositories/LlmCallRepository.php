<?php

declare(strict_types=1);

namespace Domain\Supplier\Repositories;

use Carbon\CarbonImmutable;
use Domain\Supplier\Models\LlmCall;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class LlmCallRepository
{
    /**
     * Recent calls, newest first, with supplier/document names joined in for
     * display (cross-context via the query builder, not Eloquent relations).
     */
    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return LlmCall::query()
            ->leftJoin('suppliers', 'suppliers.id', '=', 'llm_calls.supplier_id')
            ->leftJoin('supplier_documents', 'supplier_documents.id', '=', 'llm_calls.supplier_document_id')
            ->select('llm_calls.*', 'suppliers.name as supplier_name', 'supplier_documents.file_name as document_file')
            ->orderByDesc('llm_calls.id')
            ->paginate($perPage)
            ->through(fn (LlmCall $call) => $call->getData());
    }

    /**
     * @return array{calls: int, input_tokens: int, output_tokens: int, cost_usd: float}
     */
    public function totals(?CarbonImmutable $since = null): array
    {
        $row = LlmCall::query()
            ->when($since !== null, fn ($query) => $query->where('created_at', '>=', $since))
            ->selectRaw('COUNT(*) calls, COALESCE(SUM(input_tokens),0) input_tokens, COALESCE(SUM(output_tokens),0) output_tokens, COALESCE(SUM(cost_usd),0) cost_usd')
            ->first();

        return [
            'calls' => (int) $row->calls,
            'input_tokens' => (int) $row->input_tokens,
            'output_tokens' => (int) $row->output_tokens,
            'cost_usd' => (float) $row->cost_usd,
        ];
    }

    /**
     * Spend grouped by model, all time, biggest first.
     *
     * @return Collection<int, array{model: string, calls: int, input_tokens: int, output_tokens: int, cost_usd: float}>
     */
    public function byModel(): Collection
    {
        return LlmCall::query()
            ->selectRaw('model, COUNT(*) calls, SUM(input_tokens) input_tokens, SUM(output_tokens) output_tokens, SUM(cost_usd) cost_usd')
            ->groupBy('model')
            ->orderByDesc('cost_usd')
            ->get()
            ->map(fn ($row) => [
                'model' => (string) $row->model,
                'calls' => (int) $row->calls,
                'input_tokens' => (int) $row->input_tokens,
                'output_tokens' => (int) $row->output_tokens,
                'cost_usd' => (float) $row->cost_usd,
            ]);
    }
}
