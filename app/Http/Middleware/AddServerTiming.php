<?php

namespace App\Http\Middleware;

use App\Support\QueryClock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets Server-Timing on every response: app (time since the SAPI received the request), db
 * (total time of the SQL queries) and db-count (their number), in milliseconds (design D1).
 *
 * Registered in the global middleware, right inside AddSecurityHeaders: redirects, error pages,
 * JSON and /up carry it too. The header holds durations and a count only, never a query or a value.
 *
 * app starts at REQUEST_TIME_FLOAT, set by the SAPI: in classic mode it includes the boot of
 * Laravel, which makes the gain of the worker mode visible.
 */
class AddServerTiming
{
    public function __construct(private readonly QueryClock $queryClock) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $startedAt = (float) $request->server('REQUEST_TIME_FLOAT', microtime(true));
        $appMilliseconds = max(0.0, (microtime(true) - $startedAt) * 1000);

        $response->headers->set('Server-Timing', sprintf(
            'app;dur=%s, db;dur=%s, db-count;dur=%d',
            $this->format($appMilliseconds),
            $this->format($this->queryClock->milliseconds()),
            $this->queryClock->count(),
        ));

        return $response;
    }

    /**
     * Milliseconds rounded to the hundredth, without trailing zeros: 12.5, 0.
     */
    private function format(float $milliseconds): string
    {
        return (string) round($milliseconds, 2);
    }
}
