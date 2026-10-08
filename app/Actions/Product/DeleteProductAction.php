<?php

declare(strict_types=1);

namespace App\Actions\Product;

use App\Models\Product;

class DeleteProductAction
{
    /**
     * Soft delete a product or service.
     */
    public function execute(Product $product): Product
    {
        $product->delete();

        return $product;
    }
}
