<?php

namespace App\Support;

/**
 * The queue worker's sign of life: the Unix time in seconds of its last loop or job, written to
 * config('queue.heartbeat_path').
 *
 * Only queue:work fires the events that call beat(), so the web never writes it. The container
 * healthcheck reads the same file in plain PHP, with the same 300 second threshold.
 */
final class WorkerHeartbeat
{
    /**
     * Write the current time, replacing the file in one rename so a reader never sees it half written.
     */
    public static function beat(): void
    {
        $path = self::path();
        $temporaryPath = $path.'.'.getmypid().'.tmp';

        file_put_contents($temporaryPath, (string) now()->getTimestamp());
        rename($temporaryPath, $path);
    }

    /**
     * Whether the last heartbeat is younger than the given age; false when none was written.
     */
    public static function isFresh(int $maxAgeSeconds): bool
    {
        $path = self::path();

        if (! is_file($path)) {
            return false;
        }

        return now()->getTimestamp() - (int) file_get_contents($path) < $maxAgeSeconds;
    }

    private static function path(): string
    {
        return (string) config('queue.heartbeat_path');
    }
}
