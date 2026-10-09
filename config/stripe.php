<?php

declare(strict_types=1);

return [
    'key' => env('STRIPE_KEY'),
    'secret' => env('STRIPE_SECRET'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'connect_webhook_secret' => env('STRIPE_CONNECT_WEBHOOK_SECRET', env('STRIPE_WEBHOOK_SECRET')),
    'suppress_notices' => env('STRIPE_SUPPRESS_NOTICES', true),
];
