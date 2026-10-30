<?php

use App\Domain\DomainException;
use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\AddServerTiming;
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
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Outermost, the headers reach every response, the 500 of a missing passcode included;
        // Server-Timing next, on the same responses (optimize-performance, design D1); right
        // inside, the passcode check is the first that can refuse a request.
        $middleware->prepend([
            AddSecurityHeaders::class,
            AddServerTiming::class,
            EnsurePasscodeConfigured::class,
        ]);

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

        // A business refusal: an error toast back on the previous page, or 422 for a JSON
        // request outside Inertia (ARCHITECTURE.md, design D4).
        $exceptions->render(function (DomainException $exception, Request $request) {
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['error' => $exception->getMessage()], 422);
            }

            return Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()])->back();
        });
    })->create();
