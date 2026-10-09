<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'stripe_event_id',
    'type',
    'payload',
    'status',
    'error_message',
    'stripe_event_created_at',
    'processed_at',
])]
class StripeWebhookEvent extends Model
{
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'stripe_event_created_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
