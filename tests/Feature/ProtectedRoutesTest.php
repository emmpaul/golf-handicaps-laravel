<?php

/*
 * Mutation that turns this test red: add
 * Route::get('/x', fn () => 'x')->withoutMiddleware(RequirePasscode::class);
 * to routes/web.php: GET|HEAD x then joins the open routes.
 */

use App\Http\Middleware\RequirePasscode;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

test('every route asks for the passcode session, except the open ones', function () {
    // The HTTP kernel hands the middleware groups and their priority to the router.
    app(Kernel::class);
    $router = app(Router::class);

    $openRoutes = collect($router->getRoutes()->getRoutes())
        ->reject(fn (Route $route): bool => in_array(RequirePasscode::class, $router->gatherRouteMiddleware($route), true))
        ->map(fn (Route $route): string => implode('|', $route->methods()).' '.$route->uri())
        ->sort()
        ->values()
        ->all();

    expect($openRoutes)->toBe([
        'GET|HEAD api/version',
        'GET|HEAD login',
        // Serving and upload routes of the framework's local disk (config/filesystems.php).
        'GET|HEAD storage/{path}',
        'GET|HEAD up',
        'POST login',
        'PUT storage/{path}',
    ]);
});
