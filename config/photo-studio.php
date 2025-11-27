<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Models
    |--------------------------------------------------------------------------
    |
    | Configure which OpenRouter models are used for prompt extraction (vision)
    | and image generation. These settings are REQUIRED - the application will
    | throw an error if they are not configured, ensuring explicit model choice.
    |
    */
    'models' => [
        // Vision model for analyzing product images and extracting prompts
        'vision' => env('OPENROUTER_PHOTO_STUDIO_MODEL'),

        // Image generation model for creating photorealistic renders
        'image_generation' => env('OPENROUTER_PHOTO_STUDIO_IMAGE_MODEL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    */
    'generation_disk' => env('PHOTO_STUDIO_GENERATION_DISK', 's3'),

    /*
    |--------------------------------------------------------------------------
    | Generation Defaults
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'max_tokens' => 700,
        'temperature' => 0.4,
        'max_prompt_words' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Prompts
    |--------------------------------------------------------------------------
    |
    | System and user prompts used during prompt extraction and image generation.
    | Centralizing these here makes them easier to find and modify.
    |
    */
    'prompts' => [
        /*
        |----------------------------------------------------------------------
        | Prompt Extraction
        |----------------------------------------------------------------------
        |
        | These prompts are sent to the vision model when analyzing a product
        | image to generate an image generation prompt.
        |
        */
        'extraction' => [
            'system' => <<<'PROMPT'
You are an expert visual art director and product photographer. The response must be plain text no longer than 300 words. ONLY output the prompt text, no titles, pre text or comments, nothing else.
PROMPT,

            'user' => <<<'PROMPT'
Analyze the product image to understand what kind of item it is, including its approximate size, materials,
intended use, and emotional tone (e.g. sporty, safety-focused, luxury, tech, lifestyle, beauty, etc.).
Based on that understanding, create one single, high-quality image generation prompt where the same product
(referred to as "the reference product") appears naturally and fittingly in a relevant environment, lighting
condition, and visual style that reflect its real-world context. Do not mention or describe brand names, logos,
or label text. Keep the product clearly visible and central in the scene. Do not describe the product, only
the environment to fit it.
PROMPT,
        ],

        /*
        |----------------------------------------------------------------------
        | Edit Modification Template
        |----------------------------------------------------------------------
        |
        | Template used when a user requests modifications to an existing
        | generated image. The {original_prompt} and {instruction} placeholders
        | are replaced at runtime.
        |
        */
        'edit_template' => "{original_prompt}\n\nModification requested: {instruction}",

        /*
        |----------------------------------------------------------------------
        | Image Generation
        |----------------------------------------------------------------------
        |
        | System prompt sent to the image generation model when rendering
        | the final photorealistic product image.
        |
        */
        'generation' => [
            'system' => <<<'PROMPT'
You are a senior CGI artist who specialises in photorealistic product renders. Generate exactly one high-resolution marketing image using the supplied prompt and reference photo.
PROMPT,
        ],
    ],
];
