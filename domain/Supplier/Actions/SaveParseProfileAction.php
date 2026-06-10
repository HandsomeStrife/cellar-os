<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\Supplier\Data\SupplierParseProfileData;
use Domain\Supplier\Enums\ParseMode;
use Domain\Supplier\Models\SupplierParseProfile;

/**
 * Persists a learned parse recipe as the supplier's new active profile for that
 * mode, retiring any previous one. This is the "store how we parsed it" step.
 */
class SaveParseProfileAction extends AbstractAction
{
    /**
     * @param  array<string, mixed>  $recipe
     */
    public function execute(
        int $supplierId,
        ParseMode $mode,
        array $recipe,
        ?string $model,
        ?float $confidence,
        ?int $sourceDocumentId,
        ?int $companyId = null,
    ): SupplierParseProfileData {
        // Retire only the same tenant scope's previous profile — a buyer's
        // recipe never displaces another company's or the global one.
        SupplierParseProfile::where('supplier_id', $supplierId)
            ->where('mode', $mode->value)
            ->where('is_active', true)
            ->when($companyId === null, fn ($q) => $q->whereNull('company_id'))
            ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
            ->update(['is_active' => false]);

        $profile = SupplierParseProfile::create([
            'supplier_id' => $supplierId,
            'company_id' => $companyId,
            'mode' => $mode->value,
            'recipe' => $recipe,
            'model' => $model,
            'confidence' => $confidence,
            'source_document_id' => $sourceDocumentId,
            'is_active' => true,
        ]);

        return $profile->getData();
    }
}
