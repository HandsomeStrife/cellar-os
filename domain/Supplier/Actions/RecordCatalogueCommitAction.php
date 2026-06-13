<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;

/**
 * Writes a CRM history note when reviewed wines are committed to the catalogue
 * (approve-all from the admin review screen, or the weekly refresh). Keeps the
 * supplier's note log a complete record of catalogue changes, including wines
 * archived because they dropped out of a refreshed edition.
 */
class RecordCatalogueCommitAction extends AbstractAction
{
    public function execute(int $supplierId, string $fileName, int $committed, int $archived = 0, bool $refresh = false): void
    {
        if ($committed === 0 && $archived === 0) {
            return;
        }

        $lines = [
            ($refresh ? 'Catalogue refreshed from ' : 'Catalogue updated from ').$fileName,
            $committed.' wine(s) committed/updated'.($archived > 0 ? "; {$archived} archived (dropped out of this edition, kept for history)" : '.'),
        ];

        (new AddSupplierNoteAction)->execute($supplierId, implode("\n", $lines));
    }
}
