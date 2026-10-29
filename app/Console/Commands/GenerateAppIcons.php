<?php

namespace App\Console\Commands;

use GdImage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Draws the home screen icons with GD: the flag of BrandMark and of the Next app glyph, on the
 * background of the dark design (design D10 of visual-redesign, decision Q10). Coordinates are hundredths of the side; the
 * drawing stays in the central 80 % for the round masks of Android.
 *
 * The PNG files are committed: rerun the command when the palette changes.
 */
#[Signature('app:icons {--path= : Root directory to write into (defaults to public/)}')]
#[Description('Draw the home screen icons (192, 512 and the 180 px apple-touch-icon)')]
class GenerateAppIcons extends Command
{
    /** --color-ink: the mast and the hole. */
    private const int INK = 0xF4F5F7;

    /** --color-bg: the background. */
    private const int BG = 0x060709;

    /** --color-surface-3: the green. */
    private const int SURFACE_3 = 0x191B1D;

    /** --color-signal: the pennant, the "under par" red. */
    private const int SIGNAL = 0xF66D67;

    /**
     * Side in pixels of each icon, keyed by its path under the root directory.
     *
     * @var array<string, int<1, max>>
     */
    private const array ICONS = [
        'icons/icon-192.png' => 192,
        'icons/icon-512.png' => 512,
        'apple-touch-icon.png' => 180,
    ];

    public function handle(): int
    {
        $option = $this->option('path');
        $root = is_string($option) && $option !== '' ? rtrim($option, '/') : public_path();

        foreach (self::ICONS as $file => $side) {
            $path = $root.'/'.$file;
            File::ensureDirectoryExists(dirname($path));

            if (! imagepng($this->draw($side), $path)) {
                throw new RuntimeException("Unable to write {$path}.");
            }

            $this->components->info("{$path} ({$side} px)");
        }

        return self::SUCCESS;
    }

    /**
     * A truecolor image takes 0xRRGGBB integers as colours, without allocation.
     *
     * @param  int<1, max>  $side
     */
    private function draw(int $side): GdImage
    {
        $image = imagecreatetruecolor($side, $side);
        $at = static fn (float $hundredths): int => (int) round($hundredths * $side / 100);

        imagefilledrectangle($image, 0, 0, $side - 1, $side - 1, self::BG);
        // Green, then the hole.
        imagefilledellipse($image, $at(50), $at(75), $at(64), $at(18), self::SURFACE_3);
        imagefilledellipse($image, $at(51), $at(74.5), $at(10), $at(5), self::INK);
        // Mast.
        imagefilledrectangle($image, $at(49), $at(16), $at(53) - 1, $at(75) - 1, self::INK);
        // Pennant: a rectangle, as in the Next glyph.
        imagefilledrectangle($image, $at(53), $at(16), $at(81) - 1, $at(36) - 1, self::SIGNAL);

        return $image;
    }
}
