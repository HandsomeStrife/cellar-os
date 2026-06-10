<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\Supplier\Models\ParsedWine;

/**
 * A reviewer's edit to a proposed wine before approval. Only the catalogue
 * fields in the payload are merged; identity columns are untouched.
 */
class UpdateParsedWineAction extends AbstractAction
{
    /**
     * @param  array<string, mixed>  $changes  ProductData fields to merge into the payload
     */
    public function execute(int $parsedWineId, array $changes): void
    {
        $row = ParsedWine::findOrFail($parsedWineId);

        $payload = array_merge($row->payload ?? [], $changes);
        // Never let an edit move a wine to another supplier or fake an id.
        unset($payload['id'], $payload['uuid']);
        $payload['supplier_id'] = $row->supplier_id;

        $row->update(['payload' => $payload]);
    }
}
