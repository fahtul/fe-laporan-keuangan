<?php

return [
    'base_url' => env('FINANCE_API_BASE_URL'),
    'jwt_key' => env('FINANCE_BFF_JWT_KEY'),
    'organization_id' => env('FINANCE_ORGANIZATION_ID'),

    'iss' => env('FINANCE_BFF_ISS', 'laravel-bff'),
    'aud' => env('FINANCE_BFF_AUD', 'finance-api'),
    'ttl' => (int) env('FINANCE_BFF_TTL', 300),
    'timeout' => (int) env('FINANCE_API_TIMEOUT', 15),
];
