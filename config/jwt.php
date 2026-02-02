<?php

return [
    /*
    |--------------------------------------------------------------------------
    | JWT Secret Key
    |--------------------------------------------------------------------------
    |
    | Used to sign and verify JWT tokens. Generate a secure random string
    | for production. Use: php artisan tinker -> Str::random(64)
    |
    */
    'secret' => env('JWT_SECRET') ?: env('APP_KEY'),

    /*
    |--------------------------------------------------------------------------
    | JWT Token Expiration (in seconds)
    |--------------------------------------------------------------------------
    |
    | Default: 60 * 24 * 7 = 7 days
    |
    */
    'ttl' => env('JWT_TTL', 604800),
];
