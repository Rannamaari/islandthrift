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

    'dhiraagu_sms' => [
        'base_url' => env('DHIRAAGU_SMS_BASE_URL', 'https://messaging.dhiraagu.com.mv/v1/api'),
        'auth_key' => env('DHIRAAGU_SMS_AUTH_KEY'),
        'source' => env('DHIRAAGU_SMS_SOURCE', 'Micronet'),
        'dry_run' => env('DHIRAAGU_SMS_DRY_RUN', true),
        'chunk_size' => env('DHIRAAGU_SMS_CHUNK_SIZE', 200),
        'timeout' => env('DHIRAAGU_SMS_TIMEOUT', 20),
    ],

];
