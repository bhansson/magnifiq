<?php

namespace App\Services;

use RuntimeException;

class ImageProcessor
{
    /**
     * Convert any image binary to JPEG with white background.
     *
     * @param  string  $binary  Raw image binary (PNG, GIF, WebP, JPEG, etc.)
     * @param  int|null  $maxDimension  Optional max dimension for resizing (longest edge)
     * @param  int|null  $quality  JPEG quality 0-100 (defaults to config)
     * @return string Raw JPEG binary
     *
     * @throws RuntimeException If GD extension missing or conversion fails
     */
    public function convertToJpeg(
        string $binary,
        ?int $maxDimension = null,
        ?int $quality = null
    ): string {
        $this->ensureGdExtension();

        $image = @imagecreatefromstring($binary);

        if ($image === false) {
            throw new RuntimeException('Unable to process the image format.');
        }

        $width = imagesx($image);
        $height = imagesy($image);

        // Resize if maxDimension is specified and image exceeds it
        if ($maxDimension !== null && $maxDimension > 0) {
            [$newWidth, $newHeight, $needsResize] = $this->calculateResizeDimensions($width, $height, $maxDimension);

            if ($needsResize) {
                $resized = imagescale($image, $newWidth, $newHeight, IMG_BICUBIC);
                imagedestroy($image);

                if ($resized === false) {
                    throw new RuntimeException('Failed to resize the image.');
                }

                $image = $resized;
                $width = $newWidth;
                $height = $newHeight;
            }
        }

        // Create canvas with white background for transparency handling
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, true);
        imagesavealpha($canvas, false);

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);

        $jpegQuality = $quality ?? (int) config('photo-studio.input.jpeg_quality', 90);

        ob_start();
        $written = imagejpeg($canvas, null, $jpegQuality);
        $jpegBinary = ob_get_clean();

        imagedestroy($image);
        imagedestroy($canvas);

        if ($jpegBinary === false || $written === false) {
            throw new RuntimeException('Failed to convert image to JPEG format.');
        }

        return $jpegBinary;
    }

    /**
     * Convert raw binary to base64 data URI.
     */
    public static function toDataUri(string $binary, string $mimeType = 'image/jpeg'): string
    {
        return 'data:'.$mimeType.';base64,'.base64_encode($binary);
    }

    /**
     * Ensure GD extension is available.
     *
     * @throws RuntimeException If GD extension is not loaded
     */
    private function ensureGdExtension(): void
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('GD extension is required for image processing.');
        }
    }

    /**
     * Calculate new dimensions if image exceeds max dimension.
     *
     * @return array{0: int, 1: int, 2: bool} [newWidth, newHeight, needsResize]
     */
    private function calculateResizeDimensions(int $width, int $height, int $maxDimension): array
    {
        if ($width <= $maxDimension && $height <= $maxDimension) {
            return [$width, $height, false];
        }

        // Scale proportionally based on the longest edge
        if ($width >= $height) {
            $newWidth = $maxDimension;
            $newHeight = (int) round($height * ($maxDimension / $width));
        } else {
            $newHeight = $maxDimension;
            $newWidth = (int) round($width * ($maxDimension / $height));
        }

        return [$newWidth, $newHeight, true];
    }
}
