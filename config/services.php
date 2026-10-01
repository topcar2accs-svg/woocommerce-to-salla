<?php

return [
    'salla' => [
        'app_id' => env('SALLA_APP_ID'),
        'client_id' => env('SALLA_CLIENT_ID'),
        'client_secret' => env('SALLA_CLIENT_SECRET'),
        'webhook_secret' => env('SALLA_WEBHOOK_SECRET'),
        'api_base' => env('SALLA_API_BASE', 'https://api.salla.dev/admin/v2'),
        'accounts_base' => env('SALLA_ACCOUNTS_BASE', 'https://accounts.salla.sa/oauth2'),
        'introspect_url' => env('SALLA_INTROSPECT_URL', 'https://api.salla.dev/exchange-authority/v1/introspect'),
    ],
    'woocommerce' => [
        'version' => env('WOO_API_VERSION', 'wc/v3'),
        'timeout' => env('WOO_REQUEST_TIMEOUT', 30),
    ],
];
