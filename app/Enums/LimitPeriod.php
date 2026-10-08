<?php

declare(strict_types=1);

namespace App\Enums;

enum LimitPeriod: string
{
    case None = 'none';
    case Total = 'total';
    case Monthly = 'monthly';

    /**
     * Get a human-readable label for the limit period.
     */
    public function label(): string
    {
        return match ($this) {
            self::None => 'None',
            self::Total => 'Total',
            self::Monthly => 'Monthly',
        };
    }

    /**
     * Get all enum values as an array.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
