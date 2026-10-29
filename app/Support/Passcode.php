<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The shared passcode read from APP_PASSCODE through config('app.passcode').
 *
 * A session keeps only the fingerprint, an HMAC keyed by APP_KEY: changing the passcode
 * (or the key) changes the fingerprint and ends every open session.
 */
final class Passcode
{
    /**
     * The session key that keeps the fingerprint once the passcode is accepted.
     */
    public const SESSION_KEY = 'access.passcode';

    /**
     * Whether the session of this request was opened with the configured passcode: the one check
     * behind RequirePasscode, the login page and the props shared with an open session.
     */
    public static function opened(Request $request): bool
    {
        return self::isFingerprint($request->session()->get(self::SESSION_KEY));
    }

    /**
     * Mark the session of this request as opened with the configured passcode.
     */
    public static function open(Request $request): void
    {
        $request->session()->put(self::SESSION_KEY, self::fingerprint());
    }

    /**
     * The HMAC of the configured passcode, stored in the session at login.
     */
    public static function fingerprint(): string
    {
        return self::hmac((string) config('app.passcode'));
    }

    /**
     * Whether the input is the configured passcode, compared in constant time.
     */
    public static function matches(string $input): bool
    {
        return hash_equals(self::fingerprint(), self::hmac($input));
    }

    /**
     * Whether a value read from a session is the fingerprint of the configured passcode.
     */
    private static function isFingerprint(mixed $stored): bool
    {
        return is_string($stored) && hash_equals(self::fingerprint(), $stored);
    }

    private static function hmac(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
