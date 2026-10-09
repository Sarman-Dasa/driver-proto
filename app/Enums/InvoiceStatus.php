<?php

declare(strict_types=1);

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';

    /**
     * Get the human-readable label for the invoice status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::PartiallyPaid => 'Partially Paid',
            self::Paid => 'Paid',
            self::Overdue => 'Overdue',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Determine if the invoice can be edited.
     */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Determine if the invoice can be sent.
     */
    public function canBeSent(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Determine if the invoice can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this, [self::Draft, self::Sent], true);
    }

    /**
     * Determine if the invoice can be deleted.
     */
    public function canBeDeleted(): bool
    {
        return $this === self::Draft;
    }
}
