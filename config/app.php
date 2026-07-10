<?php

return [
    'name' => env('APP_NAME', 'AsisFly'),
    'env' => env('APP_ENV', 'local'),
    'url' => env('APP_URL', ''),
    'locale' => env('DEFAULT_LOCALE', 'es_CL'),
    'timezone' => env('DEFAULT_TIMEZONE', 'America/Santiago'),
    'dynamic_navigation' => env('ASISFLY_DYNAMIC_NAVIGATION', 'true'),
    'supported_locales' => ['es_CL', 'es_MX', 'es_CO', 'es_PE', 'pt_BR', 'en_US'],
    'supported_currencies' => ['CLP', 'USD', 'MXN', 'COP', 'PEN', 'BRL', 'ARS', 'UYU'],
];
