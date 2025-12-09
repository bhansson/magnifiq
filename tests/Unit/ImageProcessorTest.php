<?php

use App\Services\ImageProcessor;

beforeEach(function () {
    $this->processor = new ImageProcessor;
});

test('converts PNG to JPEG binary', function () {
    $png = createTestPngBinary();

    $jpeg = $this->processor->convertToJpeg($png);

    // Verify JPEG magic bytes (FFD8FF)
    expect(bin2hex(substr($jpeg, 0, 3)))->toBe('ffd8ff');
});

test('converts PNG with transparency to JPEG with white background', function () {
    // Create a 10x10 PNG with transparent background and a red pixel
    $img = imagecreatetruecolor(10, 10);
    imagesavealpha($img, true);
    imagealphablending($img, false);

    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);

    $red = imagecolorallocate($img, 255, 0, 0);
    imagesetpixel($img, 5, 5, $red);

    ob_start();
    imagepng($img);
    $pngBinary = ob_get_clean();
    imagedestroy($img);

    $jpegBinary = $this->processor->convertToJpeg($pngBinary);

    // Load the resulting JPEG and check corner pixel is white (not black/transparent)
    $result = imagecreatefromstring($jpegBinary);
    $cornerColor = imagecolorat($result, 0, 0);
    $rgb = imagecolorsforindex($result, $cornerColor);
    imagedestroy($result);

    // White background should have high R, G, B values (allowing for JPEG compression artifacts)
    expect($rgb['red'])->toBeGreaterThan(250);
    expect($rgb['green'])->toBeGreaterThan(250);
    expect($rgb['blue'])->toBeGreaterThan(250);
});

test('resizes image when maxDimension is specified and exceeded', function () {
    // Create a 200x100 image
    $img = imagecreatetruecolor(200, 100);
    $blue = imagecolorallocate($img, 0, 0, 255);
    imagefill($img, 0, 0, $blue);

    ob_start();
    imagepng($img);
    $pngBinary = ob_get_clean();
    imagedestroy($img);

    $jpegBinary = $this->processor->convertToJpeg($pngBinary, maxDimension: 50);

    // Load result and check dimensions
    $result = imagecreatefromstring($jpegBinary);
    $width = imagesx($result);
    $height = imagesy($result);
    imagedestroy($result);

    // Longest edge (width) should be scaled to 50, height proportionally
    expect($width)->toBe(50);
    expect($height)->toBe(25);
});

test('does not resize when image is smaller than maxDimension', function () {
    // Create a 30x20 image
    $img = imagecreatetruecolor(30, 20);
    $green = imagecolorallocate($img, 0, 255, 0);
    imagefill($img, 0, 0, $green);

    ob_start();
    imagepng($img);
    $pngBinary = ob_get_clean();
    imagedestroy($img);

    $jpegBinary = $this->processor->convertToJpeg($pngBinary, maxDimension: 100);

    $result = imagecreatefromstring($jpegBinary);
    $width = imagesx($result);
    $height = imagesy($result);
    imagedestroy($result);

    // Should remain unchanged
    expect($width)->toBe(30);
    expect($height)->toBe(20);
});

test('resizes proportionally when height is the longest edge', function () {
    // Create a 100x200 image (height > width)
    $img = imagecreatetruecolor(100, 200);
    $red = imagecolorallocate($img, 255, 0, 0);
    imagefill($img, 0, 0, $red);

    ob_start();
    imagepng($img);
    $pngBinary = ob_get_clean();
    imagedestroy($img);

    $jpegBinary = $this->processor->convertToJpeg($pngBinary, maxDimension: 50);

    $result = imagecreatefromstring($jpegBinary);
    $width = imagesx($result);
    $height = imagesy($result);
    imagedestroy($result);

    // Longest edge (height) should be scaled to 50, width proportionally
    expect($height)->toBe(50);
    expect($width)->toBe(25);
});

test('accepts custom quality parameter', function () {
    $png = createTestPngBinary();

    // Low quality should produce smaller file
    $lowQuality = $this->processor->convertToJpeg($png, quality: 10);
    $highQuality = $this->processor->convertToJpeg($png, quality: 100);

    // With such a small test image, size difference may be minimal,
    // but both should be valid JPEGs
    expect(bin2hex(substr($lowQuality, 0, 3)))->toBe('ffd8ff');
    expect(bin2hex(substr($highQuality, 0, 3)))->toBe('ffd8ff');
});

test('toDataUri converts binary to base64 data URI', function () {
    $binary = 'test binary content';

    $dataUri = ImageProcessor::toDataUri($binary);

    expect($dataUri)->toBe('data:image/jpeg;base64,'.base64_encode($binary));
});

test('toDataUri accepts custom mime type', function () {
    $binary = 'test binary content';

    $dataUri = ImageProcessor::toDataUri($binary, 'image/png');

    expect($dataUri)->toBe('data:image/png;base64,'.base64_encode($binary));
});

test('throws exception for invalid image data', function () {
    $this->processor->convertToJpeg('not a valid image');
})->throws(RuntimeException::class, 'Unable to process the image format.');

test('converts existing JPEG without issues', function () {
    $jpeg = createTestJpegBinary();

    $result = $this->processor->convertToJpeg($jpeg);

    // Should still produce valid JPEG
    expect(bin2hex(substr($result, 0, 3)))->toBe('ffd8ff');
});
