<?php

declare(strict_types=1);

namespace Domain\Catalogue\Actions;

use Domain\Catalogue\Models\Product;
use Domain\Shared\Actions\AbstractAction;

/**
 * Archives the wines that DROPPED OUT of a supplier's refreshed list edition.
 *
 * When a new edition is approved, every wine still listed is re-upserted and
 * its source_document_id moves to the new document — so whatever still points
 * at the superseded document was not in the new edition. Those rows are
 * archived (hidden from browse/map/basket) rather than deleted, because
 * companies may reference them from inventory and orders; if a wine reappears
 * in a later edition the upsert un-archives it.
 */
class ArchiveUnseenProductsAction extends AbstractAction
{
    public function execute(int $supersededDocumentId): int
    {
        return Product::where('source_document_id', $supersededDocumentId)
            ->whereNull('archived_at')
            ->update(['archived_at' => now()]);
    }
}
