<?php

namespace App\Domain\Imports;

use App\Domain\Imports\Exceptions\UnreadablePhoto;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ExceptionInterface;

/**
 * The photo of a card as the server keeps it (design D8): decoded whatever its format, turned
 * upright by its EXIF orientation, 3000 px at most on each side, encoded in WebP without any
 * metadata. The file sent by the browser is never kept as is.
 */
final class WebpPhoto
{
    public const int MAX_SIDE = 3000;

    /**
     * The WebP bytes of the image at this path.
     *
     * @throws UnreadablePhoto when the file is not an image Imagick can decode
     */
    public static function fromPath(string $path): string
    {
        try {
            // autoOrientation, on by default, applies the EXIF orientation at decoding.
            return (string) ImageManager::usingDriver(Driver::class, autoOrientation: true)
                ->decodePath($path)
                ->scaleDown(self::MAX_SIDE, self::MAX_SIDE)
                ->encode(new WebpEncoder(quality: 90, strip: true));
        } catch (ExceptionInterface) {
            throw new UnreadablePhoto;
        }
    }
}
