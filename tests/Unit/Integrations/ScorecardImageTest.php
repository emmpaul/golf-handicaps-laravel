<?php

/*
 * Spec scorecard-ai-completion « Données envoyées à l'IA »; design D3: the photo of an import,
 * a WebP of the photos disk, becomes a JPEG of 1568 px at most on its long side, without
 * metadata, never enlarged.
 *
 * Mutations that turn these tests red:
 * - scale() instead of scaleDown(): the 1200×900 photo is enlarged to 1568×1176;
 * - the JPEG encoded without strip: the EXIF profile of the WebP comes along;
 * - the bound applied to the width only (scaleDown(1568)): the portrait stays 1568×2091.
 */

use App\Integrations\Ai\ScorecardImage;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

pest()->extend(TestCase::class);

/**
 * Writes on the photos disk a grey WebP of this size carrying an EXIF profile (Orientation 1),
 * as a photo from a camera would.
 */
function webpWithExif(string $path, int $width, int $height): void
{
    $image = new Imagick;
    $image->newImage($width, $height, 'gray');
    $image->setImageFormat('webp');
    $image->setImageProfile('exif', "Exif\x00\x00MM\x00\x2A\x00\x00\x00\x08\x00\x01\x01\x12\x00\x03\x00\x00\x00\x01\x00\x01\x00\x00\x00\x00\x00\x00");

    Storage::disk('photos')->put($path, $image->getImageBlob());
}

beforeEach(function () {
    Storage::fake('photos');
});

test('the photo sent to the AI is a JPEG without metadata, its long side bounded to 1568 px', function (int $width, int $height, array $sent) {
    webpWithExif('imports/photo.webp', $width, $height);

    $jpeg = ScorecardImage::forReading('imports/photo.webp');

    try {
        $image = new Imagick($jpeg);

        expect($image->getImageMimeType())->toBe('image/jpeg')
            ->and([$image->getImageWidth(), $image->getImageHeight()])->toBe($sent)
            ->and($image->getImageProfiles('*', false))->toBe([]);
    } finally {
        @unlink($jpeg);
    }
})->with([
    '3000×2250 reduced' => [3000, 2250, [1568, 1176]],
    '2250×3000 portrait reduced' => [2250, 3000, [1176, 1568]],
    '1200×900 kept' => [1200, 900, [1200, 900]],
]);
