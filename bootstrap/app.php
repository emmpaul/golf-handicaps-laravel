<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsurePasscodeConfigured;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequirePasscode;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Outermost, the headers reach every response, the 500 of a missing passcode included;
        // right inside, the passcode check is the first that can refuse a request.
        $middleware->prepend([AddSecurityHeaders::class, EnsurePasscodeConfigured::class]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            RequirePasscode::class,
        ]);

        // The passcode check needs the session, and runs before the CSRF check: after a logout,
        // a write carrying the former session's token gets 401, not 419.
        $middleware->appendToPriorityList(after: StartSession::class, append: RequirePasscode::class);
        $middleware->appendToPriorityList(
            after: [RequirePasscode::class, ShareErrorsFromSession::class],
            append: PreventRequestForgery::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A wrong passcode never comes back in the flashed old input.
        $exceptions->dontFlash('passcode');

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
