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

    'mailjet' => [
        'key' => env('MAILJET_API_KEY'),
        'secret' => env('MAILJET_SECRET_KEY'),
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
    | Supported providers: openai, gemini, anthropic
    |
    */

    'ai' => [
        // Default AI provider: 'openai', 'gemini', or 'anthropic'
        'default' => env('AI_PROVIDER', 'gemini'),

        // OpenAI Configuration
        'openai' => [
            'model' => env('OPENAI_MODEL', 'gpt-4o'),
            'temperature' => (float) env('OPENAI_TEMPERATURE', 0.7),
            'max_tokens' => (int) env('OPENAI_MAX_TOKENS', 16384),
        ],

        // Google Gemini Configuration
        'gemini' => [
            'api_key' => env('GOOGLE_AI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
            'temperature' => (float) env('GEMINI_TEMPERATURE', 0.7),
            'max_tokens' => (int) env('GEMINI_MAX_TOKENS', 16384),
        ],

        // Anthropic (Claude) Configuration
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
            'temperature' => (float) env('ANTHROPIC_TEMPERATURE', 0.7),
            'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 16384),
        ],

        // Embeddings Configuration
        'embeddings' => [
            // Provider for embeddings: 'gemini' or 'openai'
            'provider' => env('EMBEDDINGS_PROVIDER', 'gemini'),

            // Gemini Embedding Configuration
            'gemini' => [
                'model' => env('GEMINI_EMBEDDING_MODEL', 'text-embedding-004'),
                'dimensions' => 768, // Gemini text-embedding-004 outputs 768 dimensions
            ],

            // OpenAI Embedding Configuration (fallback)
            'openai' => [
                'model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
                'dimensions' => 1536, // OpenAI text-embedding-3-small outputs 1536 dimensions
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Anthropic (Claude) Configuration
    |--------------------------------------------------------------------------
    */

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
        'temperature' => (float) env('ANTHROPIC_TEMPERATURE', 0.7),
        'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 16384),
    ],

];
