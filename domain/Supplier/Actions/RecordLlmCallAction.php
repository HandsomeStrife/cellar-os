<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\Supplier\Data\LlmCallData;
use Domain\Supplier\Models\LlmCall;

/**
 * Appends one row to the LLM cost ledger. Called best-effort from
 * ClaudeClient after every billable API call.
 */
class RecordLlmCallAction extends AbstractAction
{
    public function execute(LlmCallData $data): LlmCallData
    {
        return LlmCall::create([
            'purpose' => $data->purpose,
            'model' => $data->model,
            'input_tokens' => $data->input_tokens,
            'output_tokens' => $data->output_tokens,
            'cost_usd' => $data->cost_usd,
            'supplier_id' => $data->supplier_id,
            'supplier_document_id' => $data->supplier_document_id,
        ])->getData();
    }
}
