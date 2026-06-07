<?php

declare(strict_types=1);

namespace Domain\Catalogue\Actions;

use Domain\Catalogue\Data\ProductData;
use Domain\Catalogue\Models\Product;
use Domain\Shared\Actions\AbstractAction;

class CreateProductAction extends AbstractAction
{
    public function execute(ProductData $data): ProductData
    {
        $product = Product::create([
            'supplier_id' => $data->supplier_id,
            'raw_upload_id' => $data->raw_upload_id,
            'wine_name' => $data->wine_name,
            'producer' => $data->producer,
            'country' => $data->country,
            'region' => $data->region,
            'sub_region' => $data->sub_region,
            'grape' => $data->grape,
            'colour' => $data->colour,
            'vintage' => $data->vintage,
            'format_ml' => $data->format_ml,
            'case_size' => $data->case_size,
            'unit_price' => $data->unit_price,
            'price_per_litre' => $data->price_per_litre,
            'stock' => $data->stock,
            'latitude' => $data->latitude,
            'longitude' => $data->longitude,
        ]);

        return $product->getData();
    }
}
