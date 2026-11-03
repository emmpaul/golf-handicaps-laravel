<?php

/*
 * Mutations that turn these tests red:
 * - have SentryEventRecorder::install() set a hub on a client of its own, from
 *   ClientBuilder::create(): the provider no longer sets the environment, which reads production
 *   instead of testing, and http_timeout is 5 instead of 2.
 * - drop the boot of the Sentry provider from install(): the hub stays the one without a DSN, and
 *   no event reaches the recorder.
 * - rebuild the hub in install() without booting the provider: the middleware that hands the
 *   request to the SDK is missing, and the event of the HTTP test has no request.
 * - drop send() from keeping the event: the event count is 0.
 */

use Illuminate\Support\Facades\Route;
use Sentry\Laravel\Integration;
use Sentry\SentrySdk;
use Sentry\State\HubInterface;
use Tests\Support\Sentry\SentryEventRecorder;

test('a capture through the entry point of the SDK reaches a client built from the project configuration', function () {
    $recorder = SentryEventRecorder::install();

    expect($recorder->events())->toBe([]);

    Integration::captureUnhandledException(new RuntimeException('Boom'));

    expect($recorder->events())->toHaveCount(1)
        ->and($recorder->payload()['exception']['values'][0]['type'])->toBe('RuntimeException')
        ->and($recorder->payload()['environment'])->toBe('testing')
        ->and($recorder->serialized())->toContain('"dsn":"https:\/\/public@sentry.invalid\/1"')
        ->and(SentrySdk::getCurrentHub())->toBe(app(HubInterface::class))
        ->and(SentrySdk::getCurrentHub()->getClient()?->getOptions()->getHttpTimeout())->toBe(2.0)
        ->and(SentrySdk::getCurrentHub()->getClient()?->getOptions()->getHttpConnectTimeout())->toBe(1.0);
});

test('a capture during a request carries that request, as with a DSN set at boot', function () {
    $recorder = SentryEventRecorder::install();
    Route::post('/recorder-probe', function (): string {
        Integration::captureUnhandledException(new RuntimeException('Boom'));

        return 'captured';
    });

    $this->post('/recorder-probe')->assertOk();

    expect($recorder->events())->toHaveCount(1)
        ->and($recorder->payload()['request']['method'])->toBe('POST')
        ->and($recorder->payload()['request']['url'])->toEndWith('/recorder-probe');
});
