<?php

namespace App\Support;

/**
 * The shared passcode read from APP_PASSCODE through config('app.passcode').
 *
 * A session keeps only the fingerprint, an HMAC keyed by APP_KEY: changing the passcode
 * (or the key) changes the fingerprint and ends every open session.
 */
final class Passcode
{
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
    public static function isFingerprint(mixed $stored): bool
    {
        return is_string($stored) && hash_equals(self::fingerprint(), $stored);
    }

    private static function hmac(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
