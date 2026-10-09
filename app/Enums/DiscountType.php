<?php

declare(strict_types=1);

namespace App\Enums;

enum DiscountType: string
{
    case None = 'none';
    case Percentage = 'percentage';
    case Fixed = 'fixed';

    /**
     * Get the human-readable label for the discount type.
     */
    public function label(): string
    {
        return match ($this) {
            self::None => 'No Discount',
            self::Percentage => 'Percentage',
            self::Fixed => 'Fixed Amount',
        };
    }
}
