<?php

declare(strict_types=1);

namespace App\Actions\Product;

use App\Models\Product;

class UpdateProductAction
{
    /**
     * Update an existing product or service.
     * Prevents modifying company_id.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(Product $product, array $data): Product
    {
        // Guard against workspace ownership tampering
        unset($data['company_id'], $data['id']);

        // Normalize price to minor units (cents) if provided
        if (isset($data['price'])) {
            $rawPrice = $data['price'];
            if (is_float($rawPrice) || (is_string($rawPrice) && str_contains($rawPrice, '.'))) {
                $data['price'] = (int) round(((float) $rawPrice) * 100);
            } else {
                $data['price'] = (int) $rawPrice;
            }
        }

        $product->update($data);

        return $product->fresh();
    }
}
