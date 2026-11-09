<?php
declare(strict_types=1);

return [
    'app_base_url' => rtrim((string) getenv('APP_BASE_URL'), '/'),
    'db' => [
        'host' => (string) getenv('DB_HOST'),
        'name' => (string) getenv('DB_NAME'),
        'user' => (string) getenv('DB_USER'),
        'password' => (string) getenv('DB_PASSWORD'),
        'charset' => 'utf8mb4',
    ],
    'stripe_secret_key' => (string) getenv('STRIPE_SECRET_KEY'),
    'stripe_webhook_secret' => (string) getenv('STRIPE_WEBHOOK_SECRET'),
    'stripe_shipping_rate_id' => (string) getenv('STRIPE_SHIPPING_RATE_ID'),
    'stripe_automatic_tax' => filter_var(getenv('STRIPE_AUTOMATIC_TAX') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    'printful_api_token' => (string) getenv('PRINTFUL_API_TOKEN'),
    'cj_api_key' => (string) getenv('CJ_API_KEY'),
];