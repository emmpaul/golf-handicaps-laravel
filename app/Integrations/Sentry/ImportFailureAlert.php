<?php

namespace App\Integrations\Sentry;

use Sentry\Event;
use Sentry\SentrySdk;
use Sentry\Severity;

/**
 * The alert of an import that ends failed without an exception (design D5 of error-alerts). Its
 * reason is the text shown to the person, with no data of a player, and the fingerprint keeps
 * every alert of a reason in one issue of Sentry.
 *
 * The event goes through SentryEventFilter like any other. It cannot make the job fail:
 * Client::captureEvent absorbs what the transport returns or throws.
 */
final class ImportFailureAlert
{
    public static function send(string $reason): void
    {
        SentrySdk::getCurrentHub()->captureEvent(
            Event::createEvent()
                ->setMessage('Import en échec : '.$reason)
                ->setLevel(Severity::error())
                ->setFingerprint(['import-failed', $reason]),
        );
    }
}
