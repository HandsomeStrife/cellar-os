<?php

declare(strict_types=1);

namespace Domain\Catalogue\Actions;

use Domain\Catalogue\Models\Product;
use Domain\Shared\Actions\AbstractAction;

class DeleteProductAction extends AbstractAction
{
    public function execute(int $id): void
    {
        // order_items / inventory_items reference product_id with nullOnDelete.
        Product::findOrFail($id)->delete();
    }
}
