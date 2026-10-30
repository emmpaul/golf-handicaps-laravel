<?php

/*
 * Spec app-shell (optimize-performance), « Icône à l'adresse par défaut »; design D6.
 *
 * /favicon.ico is a file of public/, served by Caddy before index.php like robots.txt: no PHP,
 * no session. The 200 without Set-Cookie behind Caddy is checked by the browser pass (task 7.3);
 * here, no Laravel route answers the address and the file is a real icon.
 *
 * Mutations that turn these tests red:
 * - public/favicon.ico deleted: the address then falls to the Laravel fallback, which opens a
 *   session and redirects to /login;
 * - a Route::get('/favicon.ico', …) added to routes/web.php.
 */

use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

test('no Laravel route of its own answers /favicon.ico', function () {
    $uris = collect(Route::getRoutes()->getRoutes())->map(fn (RoutingRoute $route): string => $route->uri());

    expect($uris)->not->toContain('favicon.ico')
        ->and(Route::getRoutes()->match(Request::create('/favicon.ico'))->isFallback)->toBeTrue();
});

test('public/favicon.ico is an icon of 16 and 32 pixels', function () {
    $path = public_path('favicon.ico');
    expect($path)->toBeFile();

    $bytes = (string) file_get_contents($path);
    $header = unpack('vreserved/vtype/vcount', $bytes);
    $sides = array_map(
        fn (int $entry): int => ord($bytes[6 + 16 * $entry]),
        range(0, 1),
    );

    expect(mime_content_type($path))->toBe('image/vnd.microsoft.icon')
        ->and($header)->toBe(['reserved' => 0, 'type' => 1, 'count' => 2])
        ->and($sides)->toBe([16, 32]);
});
