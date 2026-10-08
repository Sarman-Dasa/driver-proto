<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductType: string
{
    case Product = 'product';
    case Service = 'service';

    /**
     * Get a human-readable display label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Product => 'Product',
            self::Service => 'Service',
        };
    }
}
