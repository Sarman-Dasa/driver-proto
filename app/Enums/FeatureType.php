<?php

declare(strict_types=1);

namespace App\Enums;

enum FeatureType: string
{
    case Boolean = 'boolean';
    case Limit = 'limit';

    /**
     * Get a human-readable label for the feature type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Boolean => 'Boolean Feature',
            self::Limit => 'Usage Limit Feature',
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
