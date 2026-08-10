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
            'sms' => env('SMS_WEBHOOK_SECRET'),
            'fcm' => env('FCM_WEBHOOK_SECRET'),
        ],
    ],

];
