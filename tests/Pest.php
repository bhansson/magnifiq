<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create a minimal valid PNG binary for testing.
 */
function createTestPngBinary(): string
{
    $img = imagecreatetruecolor(1, 1);
    imagefilledrectangle($img, 0, 0, 1, 1, imagecolorallocate($img, 255, 0, 0));

    ob_start();
    imagepng($img);
    $binary = ob_get_clean();
    imagedestroy($img);

    return $binary;
}

/**
 * Create a minimal valid JPEG binary for testing.
 */
function createTestJpegBinary(): string
{
    $img = imagecreatetruecolor(1, 1);
    $red = imagecolorallocate($img, 255, 0, 0);
    imagesetpixel($img, 0, 0, $red);

    ob_start();
    imagejpeg($img, null, 100);
    $jpeg = ob_get_clean();
    imagedestroy($img);

    return $jpeg;
}

/**
 * Create a base64 encoded test JPEG image.
 */
function createTestJpegBase64(): string
{
    return base64_encode(createTestJpegBinary());
}

/**
 * Create a data URI for a test JPEG image.
 */
function createTestJpegDataUri(): string
{
    return 'data:image/jpeg;base64,' . createTestJpegBase64();
}

/**
 * Get the configured image generation model for Photo Studio tests.
 */
function imageGenerationModel(): string
{
    return config('photo-studio.models.image_generation');
}

/**
 * Create a minimal valid PNG as base64 (blue pixel for differentiation).
 */
function createTestPngBase64(): string
{
    $img = imagecreatetruecolor(1, 1);
    $blue = imagecolorallocate($img, 0, 0, 255);
    imagesetpixel($img, 0, 0, $blue);

    ob_start();
    imagepng($img);
    $png = ob_get_clean();
    imagedestroy($img);

    return base64_encode($png);
}
