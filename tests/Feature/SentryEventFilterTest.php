<?php

/*
 * Mutations of App\Integrations\Sentry\SentryEventFilter that turn these tests red (design D2 of
 * error-alerts). The filter builds a new event and copies only what it allows:
 * - keep the message of an exception (leave its value alone): the first test finds Manu and a
 *   value other than '', the second one finds Sean.
 * - copy the message of the event when it holds an exception: the message test finds Manu.
 * - drop the message of an event without exception: the reason test reads no message.
 * - drop the level of the event: the reason test reads no level.
 * - drop the fingerprint of an event without exception: the reason test reads none.
 * - copy the params and the formatted text of the message of an event without exception: the
 *   params test reads a message array instead of the literal, and finds both witnesses.
 * - copy the frames as they are: vars, context_line, pre_context, post_context and abs_path
 *   come back in the keys of the frames, and the source line of the test holds Manu.
 * - keep the mechanism as it is: the test of the mechanism reads the data with the code 98765.
 * - keep the query_string, data, cookies, headers or env of the request, or the whole url: the
 *   request is no longer the literal method and path. Cookies and env only come from the SDK
 *   under send_default_pii, hence the test that turns it on.
 * - rtrim the path of its slash: the home page reads '' instead of '/'.
 * - copy breadcrumbs, user, extra, tags, contexts, server_name, release, modules, transaction,
 *   logger or the SDK metadata: the keys of the payload or of the envelope header differ, and
 *   the witnesses appear in the serialized event.
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\SentrySdk;
use Sentry\Severity;
use Sentry\State\Scope;
use Tests\Support\Sentry\SentryEventRecorder;
use Tests\TestCase;

use function Sentry\configureScope;
use function Sentry\withScope;

test('a reported exception keeps its type and loses its message', function () {
    $recorder = SentryEventRecorder::install();

    report(new RuntimeException('Key (name)=(Manu) already exists'));

    expect($recorder->events())->toHaveCount(1)
        ->and($recorder->payload()['exception']['values'])->toHaveCount(1)
        ->and($recorder->payload()['exception']['values'][0]['type'])->toBe('RuntimeException')
        ->and($recorder->payload()['exception']['values'][0]['value'])->toBe('')
        ->and($recorder->serialized())->not->toContain('Manu');
});

test('each exception of a chain keeps its type and loses its message', function () {
    $recorder = SentryEventRecorder::install();

    report(new RuntimeException('Outer Manu', previous: new LogicException('Inner Sean')));

    $values = $recorder->payload()['exception']['values'];
    expect(array_column($values, 'type'))->toBe(['LogicException', 'RuntimeException'])
        ->and(array_column($values, 'value'))->toBe(['', ''])
        ->and($recorder->serialized())->not->toContain('Manu')
        ->and($recorder->serialized())->not->toContain('Sean');
});

test('an event holding an exception never carries a message of its own', function () {
    $recorder = SentryEventRecorder::install();
    $event = Event::createEvent()->setMessage('Key (name)=(Manu) already exists');

    SentrySdk::getCurrentHub()->captureEvent($event, EventHint::fromArray(['exception' => new RuntimeException('Boom')]));

    expect($recorder->payload())->not->toHaveKey('message')
        ->and($recorder->serialized())->not->toContain('Manu');
});

test('an event without exception keeps its reason, its level and its fingerprint', function () {
    $recorder = SentryEventRecorder::install();

    withScope(function (Scope $scope): void {
        $scope->setFingerprint(['import-failed', 'Budget IA du mois atteint']);
        SentrySdk::getCurrentHub()->captureEvent(
            Event::createEvent()
                ->setMessage('Import en échec : Budget IA du mois atteint')
                ->setLevel(Severity::error()),
        );
    });

    expect($recorder->payload()['message'])->toBe('Import en échec : Budget IA du mois atteint')
        ->and($recorder->payload()['fingerprint'])->toBe(['import-failed', 'Budget IA du mois atteint'])
        ->and($recorder->payload()['level'])->toBe('error');
});

test('an event without exception loses the params and the formatted text of its message', function () {
    $recorder = SentryEventRecorder::install();

    SentrySdk::getCurrentHub()->captureEvent(
        Event::createEvent()->setMessage('Import en échec : %s', ['params-witness'], 'formatted-witness'),
    );

    expect($recorder->payload()['message'])->toBe('Import en échec : %s')
        ->and($recorder->serialized())->not->toContain('params-witness')
        ->and($recorder->serialized())->not->toContain('formatted-witness');
});

test('a stack frame keeps its place in the code and nothing of its values or source', function () {
    $recorder = SentryEventRecorder::install();
    configureScope(function (Scope $scope): void {
        $scope->addEventProcessor(function (Event $event): Event {
            foreach ($event->getExceptions() as $exception) {
                foreach ($exception->getStacktrace()?->getFrames() ?? [] as $frame) {
                    $frame->setVars(['player' => 'Manu']);
                }
            }

            return $event;
        });
    });

    report(new RuntimeException('Boom Manu'));

    $frames = $recorder->payload()['exception']['values'][0]['stacktrace']['frames'];
    $keys = array_values(array_unique(array_merge(...array_map(array_keys(...), $frames))));
    sort($keys);
    expect($keys)->toBe(['filename', 'function', 'in_app', 'lineno'])
        ->and(end($frames)['filename'])->toBe('/tests/Feature/SentryEventFilterTest.php')
        ->and($recorder->serialized())->not->toContain('Manu');
});

test('the mechanism of an exception keeps its type and handled flag and loses its data', function () {
    $recorder = SentryEventRecorder::install();

    SentrySdk::getCurrentHub()->captureException(new RuntimeException('Boom', 98765));

    expect($recorder->payload()['exception']['values'][0]['mechanism'])->toBe(['type' => 'generic', 'handled' => true]);
});

/**
 * The POST of the spec: query string, body, cookie and header, on a route that throws.
 */
function postRoundWithWitnesses(TestCase $test): void
{
    Route::middleware('web')->post('/rounds', function (): never {
        throw new RuntimeException('Boom');
    });

    $test->withPasscodeSession()
        ->withUnencryptedCookie('witness_session', 'cookie-witness')
        ->withHeader('X-Witness', 'header-witness')
        // A browser sends it, and the SDK reads no body without it.
        ->withHeader('Content-Length', '29')
        ->post('/rounds?player=Manu', ['score' => 87, 'note' => 'body-witness'])
        ->assertStatus(500);
}

function expectOnlyMethodAndPathOfTheRound(SentryEventRecorder $recorder): void
{
    expect($recorder->events())->toHaveCount(1)
        ->and($recorder->payload()['request'])->toBe(['method' => 'POST', 'url' => '/rounds'])
        ->and($recorder->serialized())->not->toContain('Manu')
        ->and($recorder->serialized())->not->toContain('body-witness')
        ->and($recorder->serialized())->not->toContain('"score"')
        ->and($recorder->serialized())->not->toContain('cookie-witness')
        ->and($recorder->serialized())->not->toContain('header-witness');
}

test('a failing POST leaves only its method and its path in the event', function () {
    $recorder = SentryEventRecorder::install();

    postRoundWithWitnesses($this);

    expectOnlyMethodAndPathOfTheRound($recorder);
});

test('the filter holds the request alone when the SDK collects all of it', function () {
    // Against config/sentry.php, which stops the SDK from reading body, cookies and address.
    config(['sentry.send_default_pii' => true]);
    $recorder = SentryEventRecorder::install();
    $collected = [];
    configureScope(function (Scope $scope) use (&$collected): void {
        $scope->addEventProcessor(function (Event $event) use (&$collected): Event {
            $collected = array_keys($event->getRequest());

            return $event;
        });
    });

    postRoundWithWitnesses($this);

    expect($collected)->toContain('query_string', 'data', 'cookies', 'headers', 'env');
    expectOnlyMethodAndPathOfTheRound($recorder);
});

test('a failing login leaves the typed passcode out of the event', function () {
    $recorder = SentryEventRecorder::install();
    Route::middleware('web')->post('/login', function (): never {
        throw new RuntimeException('Boom');
    });

    $this->withPasscodeSession()
        ->withHeader('Content-Length', '20')
        ->post('/login', ['passcode' => 'birdie-2026'])
        ->assertStatus(500);

    expect($recorder->events())->toHaveCount(1)
        ->and($recorder->payload()['request'])->toBe(['method' => 'POST', 'url' => '/login'])
        ->and($recorder->serialized())->not->toContain('birdie-2026');
});

test('a failing home page keeps / as its path', function () {
    $recorder = SentryEventRecorder::install();
    Route::get('/', function (): never {
        throw new RuntimeException('Boom');
    });

    $this->get('/')->assertStatus(500);

    expect($recorder->payload()['request'])->toBe(['method' => 'GET', 'url' => '/']);
});

test('what the scope gathers before an exception stays out of the event, logs and SQL included', function () {
    // Breadcrumbs stay on here, against config/sentry.php: the filter alone must empty them.
    config([
        'sentry.breadcrumbs.logs' => true,
        'sentry.breadcrumbs.sql_queries' => true,
        'sentry.server_name' => 'server-witness',
        'sentry.release' => 'release-witness',
    ]);
    $recorder = SentryEventRecorder::install();
    $gathered = [];
    configureScope(function (Scope $scope) use (&$gathered): void {
        $scope->setUser(['username' => 'Manu']);
        $scope->setExtra('player', 'Manu');
        $scope->setTag('player', 'Manu');
        $scope->setContext('player', ['name' => 'Manu']);
        $scope->addBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'manual', 'Manu'));
        $scope->addEventProcessor(function (Event $event) use (&$gathered): Event {
            $gathered = array_map(fn (Breadcrumb $breadcrumb): ?string => $breadcrumb->getMessage(), $event->getBreadcrumbs());

            return $event->setLogger('logger-witness');
        });
    });
    Route::get('/gathered-probe', function (): never {
        Log::info('log-witness');
        DB::select('select 1 as sql_witness');

        throw new RuntimeException('Boom');
    });

    $this->get('/gathered-probe')->assertStatus(500);

    $payload = $recorder->payload();
    $keys = array_keys($payload);
    sort($keys);
    $envelopeHeader = json_decode(explode("\n", $recorder->serialized())[0], true, flags: JSON_THROW_ON_ERROR);
    expect($gathered)->toContain('Manu', 'log-witness', 'select 1 as sql_witness')
        ->and($keys)->toBe(['environment', 'exception', 'platform', 'request', 'sdk', 'timestamp'])
        ->and($payload['environment'])->toBe('testing')
        ->and(array_keys($envelopeHeader))->toBe(['sent_at', 'dsn', 'sdk', 'event_id'])
        ->and($recorder->serialized())->not->toContain('Manu')
        ->and($recorder->serialized())->not->toContain('log-witness')
        ->and($recorder->serialized())->not->toContain('sql_witness')
        ->and($recorder->serialized())->not->toContain('server-witness')
        ->and($recorder->serialized())->not->toContain('release-witness')
        ->and($recorder->serialized())->not->toContain('logger-witness');
});
