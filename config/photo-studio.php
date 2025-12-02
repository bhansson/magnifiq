<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Composition Mode Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the Composition feature that allows combining multiple
    | product images and/or uploaded images into a single generation.
    |
    */
    'composition' => [
        'max_images' => env('PHOTO_STUDIO_COMPOSITION_MAX_IMAGES', 14),

        'modes' => [
            'scene_composition' => [
                'label' => 'Scene Composition',
                'description' => 'Create a beautifully composed scene for a single product',
                'icon' => 'sparkles',
                'example_hint' => 'Perfect for: hero images, product showcases, marketing banners',
                'example_image' => 'images/composition-examples/scene-composition.svg',
                'min_images' => 1,
                'max_images' => 1,
            ],
            'lifestyle_context' => [
                'label' => 'Lifestyle Context',
                'description' => 'Show product being used by people in real-world scenarios',
                'icon' => 'users',
                'example_hint' => 'Perfect for: product-in-use shots, lifestyle marketing, social media content',
                'example_image' => 'images/composition-examples/lifestyle-context.svg',
                'min_images' => 1,
            ],
            'products_together' => [
                'label' => 'Product Group Image',
                'description' => 'All products appear in one cohesive scene',
                'icon' => 'user-group',
                'example_hint' => 'Perfect for: outfit combinations, product bundles, room setups',
                'example_image' => 'images/composition-examples/products-together.svg',
                'min_images' => 2,
            ],
            'reference_hero' => [
                'label' => 'Reference + Hero',
                'description' => 'One primary product styled using other images as mood/style reference',
                'icon' => 'viewfinder-circle',
                'example_hint' => 'Perfect for: style transfer, themed photoshoots, branded looks',
                'example_image' => 'images/composition-examples/reference-hero.png',
                'min_images' => 2,
            ],
        ],

        'extraction_prompts' => [
            'scene_composition' => <<<'PROMPT'
Analyze the product image to understand what kind of item it is, including its approximate size, materials,
intended use, and emotional tone (e.g. sporty, safety-focused, luxury, tech, lifestyle, beauty, etc.).
Based on that understanding, create one single, high-quality image generation prompt where the same product
(referred to as "the reference product") appears naturally and fittingly in a relevant environment, lighting
condition, and visual style that reflect its real-world context. Do not mention or describe brand names, logos,
or label text. Keep the product clearly visible and central in the scene. Do not describe the product, only
the environment to fit it.
Output only the prompt, no explanations or commentary.
PROMPT,

            'lifestyle_context' => <<<'PROMPT'
Analyze the product image to understand what it is, how it's used, and who would use it.
Create a lifestyle scene prompt showing the product being actively used or interacted with by a person.
The person should be naturally engaged with the product in a realistic, relatable context.
Focus on creating an authentic "product-in-use" moment that tells a story.
The product must remain clearly visible and identifiable as the focal point.
Choose an appropriate setting, lighting, and mood that matches the product's target audience.
Output only the prompt, no explanations or commentary.
PROMPT,

            'products_together' => <<<'PROMPT'
Analyze the provided product images to understand each item: its size, materials, intended use, and style.
Create a unified scene prompt where ALL products naturally coexist in the same environment.
Consider how these products would realistically be used or displayed together.
Choose an environment, lighting, and composition that makes sense for the entire collection.
Each product should be clearly visible and well-positioned relative to the others.
Do not describe individual products in detail - focus on the scene, arrangement, and atmosphere.
Output only the prompt, no explanations or commentary.
PROMPT,

            'reference_hero' => <<<'PROMPT'
The FIRST image is the hero product that must be the central focus.
The other images provide style, mood, and aesthetic reference ONLY.
Analyze the reference images for: color palette, lighting style, atmosphere, and visual tone.
Create a prompt that places the hero product in a scene inspired by the reference aesthetic.
The hero product should be prominent and clearly visible.
The scene styling (background, lighting, props) should reflect the mood of the reference images.
Do not include the reference images' subjects in the final scene - only their style influence.
Output only the prompt, no explanations or commentary.
PROMPT,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Models (Deprecated - Use config/ai.php)
    |--------------------------------------------------------------------------
    |
    | These settings are preserved for backwards compatibility but the AI
    | abstraction layer now uses config/ai.php for provider and model selection.
    |
    | To configure AI providers and models, update config/ai.php or use
    | environment variables:
    |   - AI_VISION_DRIVER / AI_VISION_MODEL
    |   - AI_IMAGE_GENERATION_DRIVER / AI_IMAGE_GENERATION_MODEL
    |
    | The application uses AI::forFeature('vision') and
    | AI::forFeature('image_generation') to resolve the appropriate adapter.
    |
    */
    'models' => [
        // Legacy: Vision model (now configured via AI_VISION_MODEL)
        'vision' => env('AI_VISION_MODEL', env('OPENROUTER_PHOTO_STUDIO_MODEL')),

        // Legacy: Image generation model (now configured via AI_IMAGE_GENERATION_MODEL)
        'image_generation' => env('AI_IMAGE_GENERATION_MODEL', env('OPENROUTER_PHOTO_STUDIO_IMAGE_MODEL')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Generation Models
    |--------------------------------------------------------------------------
    |
    | Registry of available AI models for image generation. Each model defines
    | its capabilities, supported resolutions, and pricing information.
    |
    | Pricing types:
    |   - per_image: Flat cost per generated image
    |   - per_resolution: Cost varies by selected resolution
    |   - per_megapixel: Cost based on input + output megapixels
    |
    */
    'image_models' => [
        'google/gemini-2.5-flash-image' => [
            'name' => 'Gemini Flash Image',
            'description' => 'Fast, cost-effective image generation with multi-image support',
            'provider' => 'replicate',
            'supports_resolution' => false,
            'max_images' => 14,
            'pricing' => [
                'type' => 'per_image',
                'cost' => 0.039,
            ],
        ],
        'google/nano-banana-pro' => [
            'name' => 'Nano Banana Pro',
            'description' => 'High-quality generations with 1K, 2K, and 4K resolution options',
            'provider' => 'replicate',
            'supports_resolution' => true,
            'max_images' => 14,
            'resolutions' => [
                '1K' => [
                    'label' => '1K (1024px)',
                    'description' => 'Standard quality',
                ],
                '2K' => [
                    'label' => '2K (2048px)',
                    'description' => 'High quality',
                ],
                '4K' => [
                    'label' => '4K (4096px)',
                    'description' => 'Ultra high quality',
                ],
            ],
            'default_resolution' => '1K',
            'pricing' => [
                'type' => 'per_resolution',
                'costs' => [
                    '1K' => 0.15,
                    '2K' => 0.15,
                    '4K' => 0.30,
                ],
            ],
        ],
        'bytedance/seedream-4' => [
            'name' => 'Seedream 4',
            'description' => 'ByteDance image model with customizable resolutions',
            'provider' => 'replicate',
            'supports_resolution' => true,
            'max_images' => 10,
            'resolutions' => [
                '1K' => [
                    'label' => '1K (1024px)',
                    'description' => 'Standard quality',
                ],
                '2K' => [
                    'label' => '2K (2048px)',
                    'description' => 'High quality (default)',
                ],
                '4K' => [
                    'label' => '4K (4096px)',
                    'description' => 'Ultra high quality',
                ],
            ],
            'default_resolution' => '2K',
            'pricing' => [
                'type' => 'per_image',
                'cost' => 0.03,
            ],
        ],
        'black-forest-labs/flux-2-flex' => [
            'name' => 'FLUX 2 Flex',
            'description' => 'Flexible image generation with megapixel-based pricing',
            'provider' => 'replicate',
            'supports_resolution' => true,
            'max_images' => 10,
            'resolutions' => [
                '0.5mp' => [
                    'label' => '0.5 MP (~707×707)',
                    'megapixels' => 0.5,
                    'description' => 'Fast preview',
                ],
                '1mp' => [
                    'label' => '1 MP (~1024×1024)',
                    'megapixels' => 1.0,
                    'description' => 'Standard quality',
                ],
                '2mp' => [
                    'label' => '2 MP (~1448×1448)',
                    'megapixels' => 2.0,
                    'description' => 'High quality',
                ],
                '4mp' => [
                    'label' => '4 MP (2048×2048)',
                    'megapixels' => 4.0,
                    'description' => 'Maximum quality',
                ],
            ],
            'default_resolution' => '1mp',
            'pricing' => [
                'type' => 'per_megapixel',
                'cost_per_mp' => 0.06,
            ],
        ],
        'qwen/qwen-image-edit-plus' => [
            'name' => 'Qwen Image Edit Plus',
            'description' => 'Image editing model with multi-image consistency',
            'provider' => 'replicate',
            'supports_resolution' => false,
            'max_images' => 3,
            'pricing' => [
                'type' => 'per_image',
                'cost' => 0.03,
            ],
        ],
    ],

    // Default model when none is selected (falls back to env for backwards compatibility)
    'default_image_model' => env('PHOTO_STUDIO_DEFAULT_MODEL', 'google/gemini-2.5-flash-image'),

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    */
    'generation_disk' => env('PHOTO_STUDIO_GENERATION_DISK', 's3'),

    // Disk for storing composition source images (processed uploads).
    // These are stored with private visibility for team-level access control.
    // Defaults to the generation disk to ensure consistent storage configuration.
    'source_disk' => env('PHOTO_STUDIO_SOURCE_DISK', env('PHOTO_STUDIO_GENERATION_DISK', 's3')),

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
    | Input Image Processing
    |--------------------------------------------------------------------------
    |
    | Configure how input images are processed before being sent to AI models.
    | Images larger than max_input_dimension will be resized proportionally
    | so the longest edge matches this value, preserving aspect ratio.
    |
    */
    'input' => [
        // Maximum dimension (width or height) for input images in pixels.
        // Images exceeding this will be resized while maintaining aspect ratio.
        // Set to null to disable resizing.
        'max_dimension' => env('PHOTO_STUDIO_MAX_INPUT_DIMENSION', 1500),

        // Compression quality for resized images (0-100 for JPEG/WebP, 0-9 for PNG).
        // Higher values = better quality but larger file size.
        'jpeg_quality' => env('PHOTO_STUDIO_INPUT_JPEG_QUALITY', 80),
        'webp_quality' => env('PHOTO_STUDIO_INPUT_WEBP_QUALITY', 80),
        'png_compression' => env('PHOTO_STUDIO_INPUT_PNG_COMPRESSION', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Aspect Ratio Configuration
    |--------------------------------------------------------------------------
    |
    | Configure available aspect ratios for image generation. The 'match_input'
    | option automatically detects the input image's aspect ratio and maps it
    | to the closest supported ratio.
    |
    */
    'aspect_ratios' => [
        // Default behavior - detect from input image
        'default' => 'match_input',

        // All ratios supported by OpenRouter image generation
        'available' => [
            'match_input' => [
                'label' => 'Match input image',
                'description' => 'Automatically match the aspect ratio of the source image',
            ],
            '1:1' => [
                'label' => 'Square (1:1)',
                'description' => '1024×1024 - Perfect for social media profiles',
            ],
            '4:3' => [
                'label' => 'Landscape (4:3)',
                'description' => 'Classic photo format',
            ],
            '3:2' => [
                'label' => 'Wide (3:2)',
                'description' => 'Standard DSLR format',
            ],
            '16:9' => [
                'label' => 'Widescreen (16:9)',
                'description' => 'HD video format',
            ],
            '21:9' => [
                'label' => 'Ultra-wide (21:9)',
                'description' => 'Cinematic banner format',
            ],
            '3:4' => [
                'label' => 'Portrait (3:4)',
                'description' => 'Vertical photo format',
            ],
            '2:3' => [
                'label' => 'Tall (2:3)',
                'description' => 'Vertical DSLR format',
            ],
            '9:16' => [
                'label' => 'Mobile (9:16)',
                'description' => 'Stories and vertical video',
            ],
            '4:5' => [
                'label' => 'Instagram (4:5)',
                'description' => 'Optimal for Instagram feed',
            ],
            '5:4' => [
                'label' => 'Large format (5:4)',
                'description' => '8×10 print format',
            ],
        ],
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
