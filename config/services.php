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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'payment' => [
        'gateway' => env('PAYMENT_GATEWAY', 'myfatoorah'),
        'default_currency' => env('PAYMENT_CURRENCY', 'EGP'),
        'supported_currencies' => env('PAYMENT_SUPPORTED_CURRENCIES', 'EGP'),
    ],

    'myfatoorah' => [
        'api_key' => env('PAYMENT_MYFATOORAH_API_KEY'),
        'base_url' => env('PAYMENT_MYFATOORAH_BASE_URL', 'https://apitest.myfatoorah.com'),
        'test_mode' => env('PAYMENT_MYFATOORAH_TEST_MODE', true),
        'country_iso' => env('PAYMENT_MYFATOORAH_COUNTRY_ISO', 'EGY'),
    ],

];
