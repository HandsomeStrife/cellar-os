<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\Supplier\Data\SupplierDocumentData;

/**
 * Records the outcome of a document analysis in BOTH places that matter for
 * history: the document's own status/analysis_notes, and a timestamped entry
 * in the supplier's CRM note log (so the admin console shows "what happened
 * and when" without anyone having to read the database).
 *
 * Used by every analysis path — the queued job AND the wine:parse CLI — so the
 * record exists no matter how a document was processed.
 *
 * @phpstan-param array{strategy?: string, stored?: int, flagged?: int, preview?: bool, input_tokens?: int, output_tokens?: int, cost_usd?: float, model?: string, notes?: string} $summary
 */
class RecordDocumentAnalysisAction extends AbstractAction
{
    public function execute(int $documentId, array $summary): SupplierDocumentData
    {
        $document = (new MarkDocumentAnalysedAction)->execute($documentId, $summary['notes'] ?? null);

        (new AddSupplierNoteAction)->execute(
            $document->supplier_id,
            $this->composeNote($document, $summary),
        );

        return $document;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function composeNote(SupplierDocumentData $document, array $summary): string
    {
        $method = match ((string) ($summary['strategy'] ?? 'llm')) {
            'tabular' => 'column mapping (re-imports are free)',
            'pattern' => 'deterministic pattern rules (re-imports are free)',
            default => 'LLM extraction',
        };

        $stored = (int) ($summary['stored'] ?? 0);
        $flagged = (int) ($summary['flagged'] ?? 0);
        $in = (int) ($summary['input_tokens'] ?? 0);
        $out = (int) ($summary['output_tokens'] ?? 0);
        $cost = (float) ($summary['cost_usd'] ?? 0.0);

        $lines = [
            'Document analysed: '.$document->file_name,
            'Method: '.$method.($summary['model'] ?? '' ? ' ('.$summary['model'].')' : ''),
            'Result: '.$stored.' wine(s) proposed'.($flagged > 0 ? ", {$flagged} flagged for review" : '')
                .(($summary['preview'] ?? false) ? ' (preview only — full run pending)' : ''),
            $in + $out > 0
                ? sprintf('Cost: %s in / %s out tokens (~$%.4f)', number_format($in), number_format($out), $cost)
                : 'Cost: $0 (deterministic — no API tokens)',
        ];

        return implode("\n", $lines);
    }
}
