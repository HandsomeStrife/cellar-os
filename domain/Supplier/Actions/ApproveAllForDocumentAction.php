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
    /**
     * @param  callable(int): void|null  $onProgress  Called with the running
     *                                                approved count after each chunk.
     */
    public function execute(int $documentId, bool $skipFlagged = false, ?callable $onProgress = null): int
    {
        $approve = new ApproveParsedWineAction;
        $count = 0;

        ParsedWine::where('supplier_document_id', $documentId)
            ->where('status', ParsedWineStatus::Proposed->value)
            ->when($skipFlagged, fn ($q) => $q->whereNull('flag'))
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($approve, &$count, $onProgress) {
                foreach ($rows as $row) {
                    // Policy: never bulk-commit a price-less wine. We don't carry
                    // catalogue data without a price — unpriced lists are sourced
                    // from the supplier directly. A human can still add a price in
                    // the review screen and approve that row individually.
                    if (! $this->hasPrice($row->payload['unit_price'] ?? null)) {
                        continue;
                    }

                    $approve->execute($row->id);
                    $count++;
                }

                if ($onProgress !== null) {
                    $onProgress($count);
                }
            });

        return $count;
    }

    private function hasPrice(mixed $price): bool
    {
        return $price !== null && $price !== '' && (float) $price > 0;
    }
}
