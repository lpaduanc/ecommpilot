<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
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

    /*
    |--------------------------------------------------------------------------
    | Nuvemshop Integration
    |--------------------------------------------------------------------------
    */

    'nuvemshop' => [
        'client_id' => env('NUVEMSHOP_CLIENT_ID'),
        'client_secret' => env('NUVEMSHOP_CLIENT_SECRET'),
        'redirect_uri' => env('NUVEMSHOP_REDIRECT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the AI providers for analysis and chat features.
    | Supported providers: openai, gemini
    |
    */

    'ai' => [
        // Default AI provider: 'openai' or 'gemini'
        'default' => env('AI_PROVIDER', 'openai'),

        // OpenAI Configuration
        'openai' => [
            'model' => env('OPENAI_MODEL', 'gpt-4o'),
            'temperature' => (float) env('OPENAI_TEMPERATURE', 0.7),
            'max_tokens' => (int) env('OPENAI_MAX_TOKENS', 4000),
        ],

        // Google Gemini Configuration
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
            'temperature' => (float) env('GEMINI_TEMPERATURE', 0.7),
            'max_tokens' => (int) env('GEMINI_MAX_TOKENS', 4000),
        ],
    ],

];
