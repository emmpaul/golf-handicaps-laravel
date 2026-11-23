<?php

namespace App\Integrations\Sentry;

use Throwable;
use WeakMap;

/**
 * The exceptions of the job attempts that the queue will retry (design D4 of error-alerts): the
 * worker releases the job, then reports the same exception, which SentryEventFilter then drops.
 *
 * A singleton, as the listener and the filter must read the same map. It holds no state of a
 * request: only the worker fills it, and an entry goes away with its exception, so the map keeps
 * nothing alive between two jobs or two requests.
 */
final class RetriedJobExceptions
{
    /** @var WeakMap<Throwable, true> */
    private WeakMap $exceptions;

    public function __construct()
    {
        $this->exceptions = new WeakMap;
    }

    public function remember(Throwable $exception): void
    {
        $this->exceptions[$exception] = true;
    }

    public function has(Throwable $exception): bool
    {
        return isset($this->exceptions[$exception]);
    }
}
