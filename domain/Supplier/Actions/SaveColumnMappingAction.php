<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\Supplier\Models\Supplier;

class SaveColumnMappingAction extends AbstractAction
{
    /**
     * Remember the supplier's column mapping so future imports pre-fill it.
     *
     * @param  array<string, string>  $mapping
     */
    public function execute(int $supplierId, array $mapping): void
    {
        Supplier::where('id', $supplierId)->update(['column_mapping' => $mapping]);
    }
}
