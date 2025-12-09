# ImageProcessor Service Refactoring

## Problem

Three methods across the codebase perform nearly identical image-to-JPEG conversion with white background compositing:

| Method | Location | Features | Output |
|--------|----------|----------|--------|
| `convertImageToJpegDataUri()` | PhotoStudio.php:1346 | Resize + white BG | Data URI |
| `convertBinaryToJpeg()` | PhotoStudio.php:1570 | Resize + white BG | Binary |
| `convertPngToJpg()` | GeneratePhotoStudioImage.php:301 | White BG only | Binary |

This duplication creates maintenance burden and inconsistency risk.

## Solution

Extract a unified `App\Services\ImageProcessor` service.

## API Design

```php
namespace App\Services;

class ImageProcessor
{
    /**
     * Convert any image binary to JPEG with white background.
     *
     * @param string $binary Raw image binary (PNG, GIF, WebP, etc.)
     * @param int|null $maxDimension Optional max dimension for resizing (longest edge)
     * @param int|null $quality JPEG quality 0-100 (defaults to config)
     * @return string Raw JPEG binary
     * @throws RuntimeException If GD extension missing or conversion fails
     */
    public function convertToJpeg(
        string $binary,
        ?int $maxDimension = null,
        ?int $quality = null
    ): string;

    /**
     * Convert raw binary to base64 data URI.
     */
    public static function toDataUri(string $binary, string $mimeType = 'image/jpeg'): string;
}
```

## Usage Examples

```php
// Simple conversion (Job use case)
$jpeg = app(ImageProcessor::class)->convertToJpeg($pngBinary);

// With resizing (Livewire use case)
$jpeg = app(ImageProcessor::class)->convertToJpeg($binary, maxDimension: 1024);

// Get data URI
$dataUri = ImageProcessor::toDataUri($jpeg);
```

## Implementation Details

- **Quality**: Defaults to `config('photo-studio.input.jpeg_quality', 90)`, can be overridden per call
- **White background**: Always composites onto white (handles PNG/GIF transparency)
- **Resize logic**: Proportional scaling using `IMG_BICUBIC`, only when image exceeds `maxDimension`
- **Error handling**: Throws `RuntimeException` with descriptive messages
- **Memory**: Properly cleans up GD resources with `imagedestroy()`

## Migration Plan

1. Create `app/Services/ImageProcessor.php`
2. Create `tests/Unit/ImageProcessorTest.php`
3. Update `app/Livewire/PhotoStudio.php` to use service
4. Update `app/Jobs/GeneratePhotoStudioImage.php` to use service
5. Remove duplicated methods and constants

## Testing

- Basic PNG → JPEG conversion
- Transparency handling (white background verification)
- Resizing behavior (proportional scaling)
- Quality parameter override
- Data URI helper output format
- Existing Photo Studio tests must continue passing
