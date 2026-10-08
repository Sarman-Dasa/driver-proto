<?php

declare(strict_types=1);

namespace App\Actions\Product;

use App\Models\Product;

class RestoreProductAction
{
    /**
     * Restore a soft-deleted product or service.
     */
    public function execute(Product $product): Product
    {
        $product->restore();

        return $product;
    }
}
