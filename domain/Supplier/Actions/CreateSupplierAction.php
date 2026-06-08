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
            'created_by_company_id' => $data->created_by_company_id,
            'name' => $data->name,
            'contact' => $data->contact,
            'email' => $data->email,
            'phone' => $data->phone,
            'location' => $data->location,
            'address' => $data->address,
            'city' => $data->city,
            'postcode' => $data->postcode,
            'country' => $data->country,
            'website' => $data->website,
            'status' => $data->status,
            'column_mapping' => $data->column_mapping,
        ]);

        return $supplier->getData();
    }
}
