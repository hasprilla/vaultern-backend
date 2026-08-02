<?php

declare(strict_types=1);

$sandbox = filter_var(env('WOMPI_SANDBOX', true), FILTER_VALIDATE_BOOL);

return [
    'enabled' => filter_var(env('WOMPI_ENABLED', false), FILTER_VALIDATE_BOOL),
    // true = sandbox API + pub_test_/prv_test_. En producción: false + llaves pub_prod_/prv_prod_.
    'sandbox' => $sandbox,
    'public_key' => env('WOMPI_PUBLIC_KEY'),
    'private_key' => env('WOMPI_PRIVATE_KEY'),
    'integrity_secret' => env('WOMPI_INTEGRITY_SECRET'),
    'events_secret' => env('WOMPI_EVENTS_SECRET'),
    'api_base' => rtrim((string) (
        env('WOMPI_API_BASE')
        ?: ($sandbox ? 'https://sandbox.wompi.co/v1' : 'https://production.wompi.co/v1')
    ), '/'),
    'checkout_url' => rtrim((string) env('WOMPI_CHECKOUT_URL', 'https://checkout.wompi.co/p/'), '/').'/',
];
