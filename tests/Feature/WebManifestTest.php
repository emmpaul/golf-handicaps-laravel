<?php

/*
 * Spec visual-theme (visual-redesign), « Couleurs du manifest »: « Manifest servi »; design D10.
 *
 * Mutation that turns this test red: "background_color" left at the former paper "#F4F7FB" in
 * public/manifest.webmanifest.
 */

test('the web manifest declares the installable application', function () {
    $manifest = json_decode(
        (string) file_get_contents(public_path('manifest.webmanifest')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($manifest)->toBe([
        'name' => 'Handicaps golf',
        'short_name' => 'Golf',
        'start_url' => '/',
        'display' => 'standalone',
        'background_color' => '#060709',
        'theme_color' => '#060709',
        'lang' => 'fr',
        'icons' => [
            ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
            ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
        ],
    ]);
});
