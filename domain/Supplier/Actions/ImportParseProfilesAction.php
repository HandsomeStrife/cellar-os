<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\Supplier\Models\SupplierParseProfile;

/**
 * Restores GLOBAL (company-agnostic) parse recipes from a golden-snapshot or
 * ingestion payload — the "how to parse this supplier's lists" knowledge that
 * cost an LLM study to learn. Company-scoped profiles are tenant data and are
 * deliberately not part of the canonical set.
 */
class ImportParseProfilesAction extends AbstractAction
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, int>  $supplierIds  public supplier name => id
     */
    public function execute(array $rows, array $supplierIds): int
    {
        $count = 0;

        foreach ($rows as $row) {
            $supplierId = $supplierIds[trim((string) ($row['supplier'] ?? ''))] ?? null;
            $mode = (string) ($row['mode'] ?? '');
            $recipe = $row['recipe'] ?? null;

            if ($supplierId === null || ! in_array($mode, ['tabular', 'document'], true) || ! is_array($recipe)) {
                continue;
            }

            try {
                SupplierParseProfile::updateOrCreate(
                    ['supplier_id' => $supplierId, 'mode' => $mode, 'company_id' => null, 'is_active' => true],
                    [
                        'recipe' => $recipe,
                        'model' => is_string($row['model'] ?? null) ? $row['model'] : null,
                        'confidence' => is_numeric($row['confidence'] ?? null) ? (float) $row['confidence'] : null,
                    ],
                );
                $count++;
            } catch (\Throwable) {
                // malformed row — skip, never abort the import
            }
        }

        return $count;
    }
}
