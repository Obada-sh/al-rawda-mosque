<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    */

    'name' => env('APP_NAME', 'Rawda'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment / Debug / URL
    |--------------------------------------------------------------------------
    */

    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://127.0.0.1:8000'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Attendance is recorded against "today", so the timezone must be the
    | mosque's local timezone and the machine clock must be correct.
    |
    */

    'timezone' => env('APP_TIMEZONE', 'Asia/Damascus'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale
    |--------------------------------------------------------------------------
    */

    'locale' => env('APP_LOCALE', 'ar'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'ar_SA'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
