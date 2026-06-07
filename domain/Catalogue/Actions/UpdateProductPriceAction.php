<?php

declare(strict_types=1);

namespace Domain\Catalogue\Actions;

use Domain\Catalogue\Data\ProductData;
use Domain\Catalogue\Models\Product;
use Domain\Shared\Actions\AbstractAction;

class UpdateProductPriceAction extends AbstractAction
{
    public function execute(int $id, float $unitPrice): ProductData
    {
        $product = Product::findOrFail($id);

        // Keep price-per-litre in step with the unit price + bottle format.
        $pricePerLitre = $product->format_ml > 0
            ? round($unitPrice / ($product->format_ml / 1000), 2)
            : null;

        $product->update([
            'unit_price' => $unitPrice,
            'price_per_litre' => $pricePerLitre,
        ]);

        return $product->getData();
    }
}
