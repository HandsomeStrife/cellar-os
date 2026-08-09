<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Catalogue\Enums\WineSubType;
use Domain\Catalogue\Enums\WineType;
use Domain\Shared\Actions\AbstractAction;
use Domain\Supplier\Models\Supplier;

/**
 * Remember how this supplier's wine-type words translate into ours, so the
 * next edition of their list imports without a human repeating the work.
 *
 * Merges into whatever is already stored rather than replacing it — a reviewer
 * usually maps the one or two labels in front of them, not the whole history.
 *
 * An entry with a null type is PENDING: a label we met in this supplier's list
 * and couldn't place. It is kept (not deleted) so the review screen can keep
 * offering it until someone decides what it means.
 */
class SaveTypeMappingAction extends AbstractAction
{
    /**
     * @param  array<string, array{type?: string|null, sub_type?: string|null}>  $mapping
     *                                                                                     supplier's label => our type (+ optional sub-type)
     */
    public function execute(int $supplierId, array $mapping): void
    {
        $supplier = Supplier::find($supplierId);

        if ($supplier === null) {
            return;
        }

        $stored = array_change_key_case($supplier->type_mapping ?? [], CASE_LOWER);

        foreach ($mapping as $label => $target) {
            $label = mb_strtolower(trim((string) $label));

            if ($label === '') {
                continue;
            }

            $type = WineType::tryFrom((string) ($target['type'] ?? ''));

            if ($type === null) {
                // Clearing a mapping returns the label to the pending list
                // rather than losing the fact that we've seen it.
                $stored[$label] = ['type' => null, 'sub_type' => null, 'label' => $target['label'] ?? $stored[$label]['label'] ?? $label];

                continue;
            }

            // A sub-type is only kept when it actually belongs to the chosen
            // type — otherwise a changed type would leave a contradictory pair.
            $subType = WineSubType::tryFrom((string) ($target['sub_type'] ?? ''));
            $subType = $subType?->parent() === $type ? $subType : null;

            $stored[$label] = [
                'type' => $type->value,
                'sub_type' => $subType?->value,
                // The label as the supplier prints it, for display.
                'label' => $target['label'] ?? $stored[$label]['label'] ?? $label,
            ];
        }

        ksort($stored);

        $supplier->update(['type_mapping' => $stored]);
    }
}
