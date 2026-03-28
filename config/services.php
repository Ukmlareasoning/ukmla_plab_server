<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'key' => env('STRIPE_KEY'),
        'currency' => env('STRIPE_CURRENCY', 'eur'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        /** Map plan slug → Stripe Price ID (test mode prices from dashboard). DB column stripe_price_id overrides per plan. */
        'package_prices' => [
            'standard_monthly' => env('STRIPE_PRICE_STANDARD_MONTHLY'),
            'premium_quarterly' => env('STRIPE_PRICE_PREMIUM_QUARTERLY'),
        ],
    ],

];
