<?php

namespace App\Http\Middleware;

use App\Support\Passcode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes every web route to a browser whose session was not opened with the shared passcode.
 *
 * The session keeps the passcode fingerprint: once APP_PASSCODE changes, every session is refused.
 */
class RequirePasscode
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Passcode::isFingerprint($request->session()->get('access.passcode'))) {
            return $next($request);
        }

        $isPageRead = $request->isMethodSafe() && ! $request->expectsJson();

        if ($isPageRead || $request->header('X-Inertia')) {
            return redirect('/login');
        }

        return response()->json(['error' => 'Session requise'], 401);
    }
}
