<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\Supplier\Enums\ParseMode;
use Domain\Supplier\Models\SupplierParseProfile;

/**
 * Folds reviewer-approved wines back into the supplier's active recipe as worked
 * examples, so the next document is parsed even more accurately. This is the
 * "learn from how we parsed it" loop.
 */
class RefineParseProfileAction extends AbstractAction
{
    /**
     * @param  array<int, array<string, mixed>>  $approvedExamples
     */
    public function execute(int $supplierId, ParseMode $mode, array $approvedExamples, ?int $companyId = null): void
    {
        // Refine only the caller's own tenant scope — a buyer's corrections
        // (which embed their wine names/prices) stay inside their company.
        $profile = SupplierParseProfile::where('supplier_id', $supplierId)
            ->where('mode', $mode->value)
            ->where('is_active', true)
            ->when($companyId === null, fn ($q) => $q->whereNull('company_id'))
            ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
            ->latest('id')
            ->first();

        if ($profile === null || $approvedExamples === []) {
            return;
        }

        $recipe = $profile->recipe ?? [];
        // Keep only the catalogue-relevant fields, and cap to a few examples.
        $recipe['examples'] = array_map(
            fn (array $w) => array_intersect_key($w, array_flip([
                'wine_name', 'producer', 'country', 'region', 'grape', 'colour', 'vintage', 'format_ml', 'unit_price',
            ])),
            array_slice(array_values($approvedExamples), 0, 5),
        );

        $profile->update([
            'recipe' => $recipe,
            'confidence' => max((float) $profile->confidence, 0.95),
        ]);
    }
}
