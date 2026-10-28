<?php

/*
 * Mutation that turns these tests red: change GenerateAppIcons::SIGNAL (the pennant colour,
 * 0xCB1E21) to 0xCB1E22, then rerun "vendor/bin/sail artisan app:icons".
 */

use Illuminate\Support\Facades\File;

/**
 * Hex colour of the pixel at the given hundredths of the side.
 */
function pixelAt(string $path, int $xPercent, int $yPercent): string
{
    $image = imagecreatefrompng($path);
    expect($image)->not->toBeFalse();
    $side = imagesx($image);
    $rgb = imagecolorat($image, intdiv($xPercent * $side, 100), intdiv($yPercent * $side, 100));

    return sprintf('#%02X%02X%02X', ($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF);
}

/**
 * Asserts the side of each icon and the colours of its witness pixels.
 */
function expectAppIcons(string $root): void
{
    $sides = [
        'icons/icon-192.png' => 192,
        'icons/icon-512.png' => 512,
        'apple-touch-icon.png' => 180,
    ];

    foreach ($sides as $file => $side) {
        $path = $root.'/'.$file;
        [$width, $height, $type] = getimagesize($path) ?: [0, 0, 0];

        expect([$width, $height, $type])->toBe([$side, $side, IMAGETYPE_PNG], $file)
            ->and(pixelAt($path, 2, 2))->toBe('#0F1D3E', "$file corner")
            ->and(pixelAt($path, 30, 75))->toBe('#46566E', "$file green")
            ->and(pixelAt($path, 51, 40))->toBe('#F4F7FB', "$file mast")
            ->and(pixelAt($path, 67, 26))->toBe('#CB1E21', "$file pennant");
    }
}

test('the committed icons have the expected sides and palette colours', function () {
    expectAppIcons(public_path());
});

test('the app:icons command draws the same icons', function () {
    $directory = storage_path('framework/testing/app-icons-'.uniqid());

    try {
        $this->artisan('app:icons', ['--path' => $directory])->assertSuccessful();

        expectAppIcons($directory);
    } finally {
        File::deleteDirectory($directory);
    }
});
