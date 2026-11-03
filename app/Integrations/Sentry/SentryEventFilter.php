<?php

namespace App\Integrations\Sentry;

use App\Domain\DomainException;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\ExceptionDataBag;
use Sentry\ExceptionMechanism;
use Sentry\Frame;
use Sentry\Stacktrace;
use Throwable;

/**
 * The only control over what leaves for Sentry (design D2 of error-alerts), as before_send: the
 * event sent is a new one, which receives only what this class copies. A field that a future SDK
 * adds to the event therefore stays behind.
 *
 * Copied: the identifier, timestamp, level, environment and SDK name of the event, the method and
 * path of the request, then either the exceptions (type, frames, mechanism type and handled flag)
 * or, for an event without exception, its message and fingerprint.
 *
 * It must not throw: the SDK would absorb the error and lose the event.
 */
final class SentryEventFilter
{
    public static function beforeSend(Event $event, ?EventHint $hint): ?Event
    {
        if (self::isExpected($hint?->exception)) {
            return null;
        }

        $allowed = Event::createEvent($event->getId())
            ->setTimestamp($event->getTimestamp())
            ->setLevel($event->getLevel())
            ->setEnvironment($event->getEnvironment())
            ->setSdkIdentifier($event->getSdkIdentifier())
            ->setSdkVersion($event->getSdkVersion())
            ->setRequest(self::methodAndPath($event->getRequest()));

        $exceptions = $event->getExceptions();

        if ($exceptions !== []) {
            return $allowed->setExceptions(array_map(self::withoutValues(...), $exceptions));
        }

        // The reason of a failure without exception (ImportFailureAlert), never from an exception.
        $message = $event->getMessage();

        if ($message !== null) {
            $allowed->setMessage($message);
        }

        return $allowed->setFingerprint($event->getFingerprint());
    }

    /**
     * A business refusal, or an attempt that the queue will retry (design D4).
     */
    private static function isExpected(?Throwable $exception): bool
    {
        return $exception instanceof DomainException
            || ($exception !== null && app(RetriedJobExceptions::class)->has($exception));
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array<string, string>
     */
    private static function methodAndPath(array $request): array
    {
        $url = $request['url'] ?? null;

        return array_filter(
            [
                'method' => $request['method'] ?? null,
                'url' => is_string($url) ? parse_url($url, PHP_URL_PATH) : null,
            ],
            is_string(...),
        );
    }

    private static function withoutValues(ExceptionDataBag $exception): ExceptionDataBag
    {
        $exception->setValue('');

        $stacktrace = $exception->getStacktrace();

        if ($stacktrace !== null) {
            $exception->setStacktrace(new Stacktrace(array_map(
                fn (Frame $frame): Frame => new Frame(
                    $frame->getFunctionName(),
                    $frame->getFile(),
                    $frame->getLine(),
                    inApp: $frame->isInApp(),
                ),
                $stacktrace->getFrames(),
            )));
        }

        $mechanism = $exception->getMechanism();

        return $exception->setMechanism(
            $mechanism === null ? null : new ExceptionMechanism($mechanism->getType(), $mechanism->isHandled()),
        );
    }
}
