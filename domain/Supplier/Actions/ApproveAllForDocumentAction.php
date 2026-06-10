<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\Supplier\Enums\ParsedWineStatus;
use Domain\Supplier\Models\ParsedWine;

/**
 * Approves every still-proposed wine for a document into the catalogue.
 * Optionally skips flagged rows (suspicious price / suspected heading / …) so
 * an unattended bulk approve never commits the rows that needed human eyes.
 */
class ApproveAllForDocumentAction extends AbstractAction
{
    public function execute(int $documentId, bool $skipFlagged = false): int
    {
        $approve = new ApproveParsedWineAction;
        $count = 0;

        ParsedWine::where('supplier_document_id', $documentId)
            ->where('status', ParsedWineStatus::Proposed->value)
            ->when($skipFlagged, fn ($q) => $q->whereNull('flag'))
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($approve, &$count) {
                foreach ($rows as $row) {
                    $approve->execute($row->id);
                    $count++;
                }
            });

        return $count;
    }
}
