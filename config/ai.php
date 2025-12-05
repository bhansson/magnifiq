<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | This option controls the default AI provider that will be used when
    | no specific provider is requested. Supported: "openai", "openrouter", "replicate"
    |
    */

    'default' => env('AI_DEFAULT_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Feature Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which AI provider and model to use for each feature.
    | Each feature can independently use a different provider.
    |
    | Note: Image generation is configured per-model in config/photo-studio.php
    | using the 'provider' key. Each model specifies its own provider.
    |
    */

    'features' => [

        'chat' => [
            'driver' => env('AI_CHAT_DRIVER', 'openai'),
            'model' => env('AI_CHAT_MODEL', 'openai/gpt-5'),
        ],

        'vision' => [
            'driver' => env('AI_VISION_DRIVER', 'openai'),
            'model' => env('AI_VISION_MODEL', 'openai/gpt-4.1'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Configure credentials and settings for each AI provider.
    |
    */

    'providers' => [

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'timeout' => env('OPENAI_REQUEST_TIMEOUT', 120),
        ],

        'openrouter' => [
            'api_key' => env('OPENROUTER_API_KEY'),
            'api_endpoint' => env('OPENROUTER_API_ENDPOINT', 'https://openrouter.ai/api/v1/'),
            'timeout' => env('OPENROUTER_API_TIMEOUT', 120),
            'title' => env('OPENROUTER_API_TITLE'),
            'referer' => env('OPENROUTER_API_REFERER'),
        ],

        'replicate' => [
            'api_key' => env('REPLICATE_API_KEY'),
            'api_endpoint' => env('REPLICATE_API_ENDPOINT', 'https://api.replicate.com/v1/'),
            'timeout' => env('REPLICATE_TIMEOUT', 60),
            'polling_timeout' => env('REPLICATE_POLLING_TIMEOUT', 300),
            'polling_interval' => env('REPLICATE_POLLING_INTERVAL', 2.0),
        ],

    ],

];
