<?php

declare(strict_types=1);

namespace App\Enums;

enum InvoiceDeliveryStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';

    /**
     * Get the human-readable label for the delivery status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Sent => 'Sent',
            self::Failed => 'Failed',
        };
    }
}
