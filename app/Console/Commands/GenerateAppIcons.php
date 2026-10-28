<?php

namespace App\Console\Commands;

use GdImage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Draws the home screen icons with GD: the green flag of BrandMark and of the Next app glyph,
 * on an ink background, in the light palette. Coordinates are hundredths of the side; the
 * drawing stays in the central 80 % for the round masks of Android.
 *
 * The PNG files are committed: rerun the command when the palette changes.
 */
#[Signature('app:icons {--path= : Root directory to write into (defaults to public/)}')]
#[Description('Draw the home screen icons (192, 512 and the 180 px apple-touch-icon)')]
class GenerateAppIcons extends Command
{
    /** --color-paper, light mode: the mast and the hole. */
    private const int PAPER = 0xF4F7FB;

    /** --color-ink, light mode: the background. */
    private const int INK = 0x0F1D3E;

    /** --color-ink-2, light mode: the green. */
    private const int INK_2 = 0x46566E;

    /** --color-signal, light mode: the pennant, the "under par" red. */
    private const int SIGNAL = 0xCB1E21;

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

        imagefilledrectangle($image, 0, 0, $side - 1, $side - 1, self::INK);
        // Green, then the hole.
        imagefilledellipse($image, $at(50), $at(75), $at(64), $at(18), self::INK_2);
        imagefilledellipse($image, $at(51), $at(74.5), $at(10), $at(5), self::PAPER);
        // Mast.
        imagefilledrectangle($image, $at(49), $at(16), $at(53) - 1, $at(75) - 1, self::PAPER);
        // Pennant: a rectangle, as in the Next glyph.
        imagefilledrectangle($image, $at(53), $at(16), $at(81) - 1, $at(36) - 1, self::SIGNAL);

        return $image;
    }
}
