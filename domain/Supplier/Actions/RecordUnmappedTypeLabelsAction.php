<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\Supplier\Models\Supplier;

/**
 * Notes type words met in a supplier's list that we couldn't place, so the
 * review screen can offer them for mapping instead of the reviewer having to
 * spot them.
 *
 * They land in the same `type_mapping` store as real mappings, with a null
 * type meaning "pending". Existing entries — mapped or pending — are never
 * touched, so re-analysing a document can't undo a human's decision.
 */
class RecordUnmappedTypeLabelsAction extends AbstractAction
{
    /**
     * @param  array<int, string>  $labels  as printed by the supplier
     */
    public function execute(?int $supplierId, array $labels): void
    {
        if ($supplierId === null || $labels === []) {
            return;
        }

        $supplier = Supplier::find($supplierId);

        if ($supplier === null) {
            return;
        }

        $stored = array_change_key_case($supplier->type_mapping ?? [], CASE_LOWER);
        $added = false;

        foreach ($labels as $label) {
            $key = mb_strtolower(trim($label));

            if ($key === '' || array_key_exists($key, $stored)) {
                continue;
            }

            $stored[$key] = ['type' => null, 'sub_type' => null, 'label' => trim($label)];
            $added = true;
        }

        if (! $added) {
            return;
        }

        ksort($stored);
        $supplier->update(['type_mapping' => $stored]);
    }
}
