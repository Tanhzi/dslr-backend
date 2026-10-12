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

    'supabase' => [
        'url' => env('SUPABASE_URL'),
        'key' => env('SUPABASE_KEY'),
    ],

    'brevo' => [
        'api_key' => env('BREVO_API_KEY'),
    ],

    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        // Model free của OpenRouter (phân cách dấu phẩy = thứ tự fallback,
        // model fail 429/rỗng thì thử model kế tiếp; controller retry 2 lượt)
        'model' => env(
            'OPENROUTER_MODEL',
            'poolside/laguna-s-2.1:free,nvidia/nemotron-3-ultra-550b-a55b:free,cohere/north-mini-code:free,dots-studio/dots-3-note-preview:free,google/gemma-4-31b-it:free,poolside/laguna-xs-2.1:free'
        ),
    ],

    'cleanup' => [
        'token' => env('CLEANUP_TOKEN'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

];
