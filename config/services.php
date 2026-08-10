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

    'whatsapp' => [
        'token' => env('WHATSAPP_CLOUD_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'mode' => env('WHATSAPP_PROVIDER', 'cloud'),
        'web' => [
            'enabled' => env('WHATSAPP_WEB_ENABLED', false),
            'endpoint' => env('WHATSAPP_WEB_ENDPOINT', 'http://whatsapp-web:3001'),
            'token' => env('WHATSAPP_WEB_TOKEN'),
        ],
    ],

    'fx' => [
        'rounding_mode' => env('FX_ROUNDING_MODE', 'half_up'),
    ],

    'frankfurter' => [
        'enabled' => env('FRANKFURTER_ENABLED', false),
        'endpoint' => env('FRANKFURTER_ENDPOINT', 'https://api.frankfurter.dev'),
        'timeout' => max(1, (int) env('FRANKFURTER_TIMEOUT', 10)),
        'quotes' => array_values(array_filter(array_map('trim', explode(',', (string) env('FRANKFURTER_QUOTES', 'LBP,USD,EUR'))))),
    ],

    'sms' => [
        'endpoint' => env('SMS_PROVIDER_ENDPOINT'),
        'token' => env('SMS_PROVIDER_TOKEN'),
        'sender' => env('SMS_PROVIDER_SENDER'),
    ],

    'fcm' => [
        'endpoint' => env('FCM_PROVIDER_ENDPOINT'),
        'token' => env('FCM_PROVIDER_TOKEN'),
    ],

    'notifications' => [
        'email_enabled' => env('NOTIFICATIONS_EMAIL_ENABLED', false),
    ],

    'webhooks' => [
        'secrets' => [
            'whatsapp' => env('WHATSAPP_WEBHOOK_SECRET'),
            'whatsapp_web' => env('WHATSAPP_WEBHOOK_SECRET'),
            'sms' => env('SMS_WEBHOOK_SECRET'),
            'fcm' => env('FCM_WEBHOOK_SECRET'),
        ],
    ],

    'external_network' => [
        'endpoint' => env('EXTERNAL_NETWORK_ENDPOINT'),
        'token' => env('EXTERNAL_NETWORK_TOKEN'),
        'timeout' => max(1, (int) env('EXTERNAL_NETWORK_TIMEOUT', 5)),
    ],

];
