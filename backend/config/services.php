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

    'openai' => [
        'key'   => env('OPENAI_API_KEY', ''),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    'nvidia' => [
        'key'   => env('NVIDIA_API_KEY', ''),
        'model' => env('NVIDIA_MODEL', 'meta/llama-3.2-11b-vision-instruct'),
        'base_url' => env('NVIDIA_BASE_URL', 'https://integrate.api.nvidia.com/v1'),
    ],

    'cloudinary' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME', ''),
        'api_key'    => env('CLOUDINARY_API_KEY', ''),
        'api_secret' => env('CLOUDINARY_API_SECRET', ''),
        'logo_id'    => env('CLOUDINARY_LOGO_ID', 'aiprice_logo'),
    ],

    // Which PaymentGateway driver handles checkout and webhooks.
    'payments' => [
        'gateway' => env('PAYMENT_GATEWAY', 'stripe'),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),                 // sk_live_… / sk_test_…
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'), // whsec_… from the webhook endpoint
    ],

    'instagram' => [
        'user_id'      => env('IG_USER_ID', ''),      // Instagram Business account ID
        'access_token' => env('IG_ACCESS_TOKEN', ''), // long-lived page access token
        'graph_version' => env('IG_GRAPH_VERSION', 'v21.0'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
