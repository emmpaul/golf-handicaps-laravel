<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the anti-framing, anti-sniffing and referrer headers on every response.
 *
 * Registered in the global middleware: error pages, redirects, /up and JSON carry them too.
 * A response that sets its own Content-Security-Policy keeps it.
 *
 * A response served in HTTPS also gets Strict-Transport-Security. isSecure() is read after
 * $next(): TrustProxies has then applied the trusted proxies, so X-Forwarded-Proto only counts
 * when a listed proxy sends it. No includeSubDomains: the parent domain hosts other services.
 */
class AddSecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'none'");
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
