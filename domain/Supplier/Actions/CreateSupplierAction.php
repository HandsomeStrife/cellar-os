<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\Supplier\Data\SupplierData;
use Domain\Supplier\Models\Supplier;

class CreateSupplierAction extends AbstractAction
{
    public function execute(SupplierData $data): SupplierData
    {
        $supplier = Supplier::create([
            'name' => $data->name,
            'contact' => $data->contact,
            'email' => $data->email,
            'phone' => $data->phone,
            'location' => $data->location,
            'status' => $data->status,
            'column_mapping' => $data->column_mapping,
        ]);

        return $supplier->getData();
    }
}
