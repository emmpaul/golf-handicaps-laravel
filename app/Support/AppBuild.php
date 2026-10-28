<?php

namespace App\Support;

/**
 * The running build: the released version and the commit it was built from.
 *
 * The single place where the commit read from APP_COMMIT is validated.
 */
final class AppBuild
{
    /**
     * The released version, read from version.txt through config('app.version').
     */
    public static function version(): string
    {
        return (string) config('app.version');
    }

    /**
     * The full lowercase 40 character sha of the build, or null when unknown or malformed.
     */
    public static function commit(): ?string
    {
        $commit = config('app.commit');

        return is_string($commit) && preg_match('/^[0-9a-f]{40}$/', $commit) === 1 ? $commit : null;
    }
}
