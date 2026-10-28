<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses every HTTP request, /up included, while no shared passcode is configured.
 *
 * The check lives in the HTTP stack, not in a service provider: artisan commands, such as
 * config:cache during the image build, keep running without the secret.
 */
class EnsurePasscodeConfigured
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (blank(config('app.passcode'))) {
            throw new RuntimeException('APP_PASSCODE must be set: the shared passcode protects every page.');
        }

        return $next($request);
    }
}
