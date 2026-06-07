<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\Supplier\Data\SupplierData;
use Domain\Supplier\Models\Supplier;

class UpdateSupplierAction extends AbstractAction
{
    public function execute(SupplierData $data): SupplierData
    {
        $supplier = Supplier::findOrFail($data->id);

        $supplier->update([
            'name' => $data->name,
            'contact' => $data->contact,
            'email' => $data->email,
            'phone' => $data->phone,
            'location' => $data->location,
            'status' => $data->status,
        ]);

        return $supplier->getData();
    }
}
