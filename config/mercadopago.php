<?php

declare(strict_types=1);

return [
    'enabled' => filter_var(env('MERCADOPAGO_ENABLED', false), FILTER_VALIDATE_BOOL),
    'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
    'public_key' => env('MERCADOPAGO_PUBLIC_KEY'),
    'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),
    'api_base' => rtrim((string) env('MERCADOPAGO_API_BASE', 'https://api.mercadopago.com'), '/'),
];
