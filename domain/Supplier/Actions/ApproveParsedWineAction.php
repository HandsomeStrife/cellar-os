<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Catalogue\Actions\UpsertProductAction;
use Domain\Catalogue\Data\ProductData;
use Domain\Shared\Actions\AbstractAction;
use Domain\Supplier\Enums\ParsedWineStatus;
use Domain\Supplier\Models\ParsedWine;

/**
 * Commits one proposed wine to the catalogue (idempotent upsert, scoped to the
 * supplier) and marks the proposal approved.
 */
class ApproveParsedWineAction extends AbstractAction
{
    public function execute(int $parsedWineId): void
    {
        $row = ParsedWine::findOrFail($parsedWineId);

        $payload = $row->payload ?? [];
        // The wine always belongs to its proposal's supplier, never whatever the
        // payload claims — keeps approval tenant-safe.
        $payload['supplier_id'] = $row->supplier_id;
        $payload['id'] = null;
        $payload['uuid'] = null;

        (new UpsertProductAction)->execute(
            ProductData::from($payload),
            sourceDocumentId: $row->supplier_document_id,
        );

        $row->update(['status' => ParsedWineStatus::Approved->value]);
    }
}
