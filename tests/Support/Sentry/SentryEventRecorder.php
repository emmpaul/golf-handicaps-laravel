<?php

namespace Tests\Support\Sentry;

use Closure;
use Illuminate\Support\Facades\Facade;
use RuntimeException;
use Sentry\Event;
use Sentry\EventType;
use Sentry\Laravel\Integration;
use Sentry\Laravel\ServiceProvider as SentryServiceProvider;
use Sentry\Laravel\Tracing\BacktraceHelper;
use Sentry\Laravel\Tracing\ServiceProvider as SentryTracingServiceProvider;
use Sentry\SentrySdk;
use Sentry\Serializer\PayloadSerializer;
use Sentry\State\HubInterface;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;

/**
 * The transport of a real Sentry client that keeps its events instead of sending them, to test
 * what leaves the application.
 *
 * install() gives the running application a dummy DSN and this transport through the sentry
 * configuration, then boots the Sentry providers again, as a boot with SENTRY_LARAVEL_DSN set
 * does. The client comes from the binding of the package and reads config/sentry.php, before_send
 * included. Its hub is the one Integration::captureUnhandledException() reads, the event handlers
 * and the request middleware are active, and the HTTP kernel of the test sends requests through
 * them. Each test starts on a new application, with no client from an earlier test.
 *
 *     $recorder = SentryEventRecorder::install();
 *     report(new RuntimeException('Key (name)=(Manu) already exists'));
 *
 *     expect($recorder->events())->toHaveCount(1)
 *         ->and($recorder->payload()['exception']['values'][0]['type'])->toBe('RuntimeException')
 *         ->and($recorder->serialized())->not->toContain('Manu');
 *
 * To test under another configuration, call config() before install(). The event handlers read
 * the logs and sql_queries breadcrumb keys at install(). The features of the package keep the
 * flags they read at the first boot, so the other keys cannot be turned on this way.
 *
 * To test a transport that fails or throws, give install() what send() does once it has kept the
 * event:
 *
 *     SentryEventRecorder::install(fn (): never => throw new RuntimeException('Unreachable'));
 *
 * serialized() also holds the envelope header, so the dummy DSN appears there: a witness value
 * searched in it must not be part of DSN.
 */
final class SentryEventRecorder implements TransportInterface
{
    public const string DSN = 'https://public@sentry.invalid/1';

    /** @var list<Event> */
    private array $sent = [];

    /**
     * @param  (Closure(): Result)|null  $outcome
     */
    private function __construct(private readonly ?Closure $outcome) {}

    /**
     * @param  (Closure(): Result)|null  $outcome  what send() does in place of a success
     */
    public static function install(?Closure $outcome = null): self
    {
        $recorder = new self($outcome);
        config(['sentry.dsn' => self::DSN, 'sentry.transport' => $recorder]);

        app()->forgetInstance(HubInterface::class);
        app()->forgetInstance(BacktraceHelper::class);
        Facade::clearResolvedInstance('sentry');
        Integration::setTransaction(null);

        app()->getProvider(SentryServiceProvider::class)?->boot();
        app()->getProvider(SentryTracingServiceProvider::class)?->boot();

        return $recorder;
    }

    public function send(Event $event): Result
    {
        $this->sent[] = $event;

        return $this->outcome === null
            ? new Result(ResultStatus::success(), $event)
            : ($this->outcome)();
    }

    public function close(?int $timeout = null): Result
    {
        return new Result(ResultStatus::success());
    }

    /**
     * The error events sent so far, after before_send. Client reports do not count: the SDK sends
     * one when it flushes after before_send has dropped an event.
     *
     * @return list<Event>
     */
    public function events(): array
    {
        return array_values(array_filter(
            $this->sent,
            fn (Event $event): bool => $event->getType() === EventType::event(),
        ));
    }

    /**
     * An event as it would go on the wire: the whole envelope, in the JSON of the SDK.
     */
    public function serialized(int $index = 0): string
    {
        $event = $this->events()[$index];
        $client = SentrySdk::getCurrentHub()->getClient()
            ?? throw new RuntimeException('Sentry has no client.');

        return (new PayloadSerializer($client->getOptions()))->serialize($event);
    }

    /**
     * The payload of serialized(), decoded: exception, request, breadcrumbs and so on.
     *
     * @return array<string, mixed>
     */
    public function payload(int $index = 0): array
    {
        $lines = explode("\n", $this->serialized($index));

        return json_decode($lines[2], true, flags: JSON_THROW_ON_ERROR);
    }
}
