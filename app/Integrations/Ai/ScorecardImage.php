<?php

namespace App\Integrations\Ai;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use RuntimeException;

/**
 * The image sent to the AI for a reading (design D3): the WebP of the import, its long side
 * bounded to 1568 px without enlarging it, as a JPEG of quality 85 without metadata. JPEG as in
 * the Next application, whose token bounds were measured with it.
 */
final class ScorecardImage
{
    public const int MAX_SIDE = 1568;

    /**
     * Writes the JPEG of this photo of the photos disk in a temporary file and returns its path;
     * the caller deletes it once sent.
     */
    public static function forReading(string $photoPath): string
    {
        $jpeg = ImageManager::usingDriver(Driver::class)
            ->decodePath(Storage::disk('photos')->path($photoPath))
            ->scaleDown(self::MAX_SIDE, self::MAX_SIDE)
            ->encode(new JpegEncoder(quality: 85, strip: true));

        $path = tempnam(sys_get_temp_dir(), 'scorecard-');
        if ($path === false) {
            throw new RuntimeException('No temporary file for the scorecard image');
        }

        file_put_contents($path, (string) $jpeg);

        return $path;
    }
}
