<?php

/*
 * Mutation that turns this test red: set "theme_color" to "#ffffff" in
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
        'background_color' => '#F4F7FB',
        'theme_color' => '#F4F7FB',
        'lang' => 'fr',
        'icons' => [
            ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
            ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
        ],
    ]);
});
