<?php

/*
 * Mutations that turn these tests red (spec error-alerts):
 * - remove Integration::handles from bootstrap/app.php: the unexpected exception sends no event.
 * - in bootstrap/app.php, capture from a reportable that returns false, in place of
 *   Integration::handles: the exception is no longer logged, and both log tests read no message.
 * - add $exceptions->stopIgnoring(HttpException::class) to bootstrap/app.php: the 404 sends an
 *   event.
 * - report a RuntimeException in RequirePasscode before it refuses: the 401 and the redirection
 *   to /login each send an event.
 * - write a DSN in config/sentry.php in place of env('SENTRY_LARAVEL_DSN'): the test without DSN
 *   sees a request leave for Sentry.
 * - read the database in SentryEventFilter::beforeSend (DB::table('jobs')->count()): the SDK absorbs
 *   the QueryException of its before_send and loses the event, so the test of the stopped database
 *   reads no event.
 *
 * Jobs, each run by the real worker (spec « Alerte sur l'échec définitif d'un job », design D4):
 * - remove the listener of JobReleasedAfterException: the first attempt of all three failing jobs
 *   sends an event where none is expected.
 * - bind RetriedJobExceptions with bind() instead of singleton(): the filter reads another
 *   instance than the listener, with the same reds.
 * - listen to JobExceptionOccurred instead, which the final attempt raises as well: the final
 *   exception is dropped, and both tests of a final failure read no event.
 * - key RetriedJobExceptions by class name: same reds, as the final exception has the class of the
 *   retried one, and the memory test takes a new instance for a retried one.
 * - set Worker::$reportJobExceptions to false, the option design D4 sets aside: the log test reads
 *   no message, and both tests of a final failure read no event.
 * - in the filter, drop what a lookup throws (SgtUnavailable): the lookup without an answer reads
 *   no event.
 * - report an exception on the way to « Saisie manuelle » in LookUpCourseRating: the manual test
 *   reads an event.
 * - hold the exceptions in a SplObjectStorage instead of a WeakMap: the memory test still reaches
 *   the exception after its last reference is gone.
 */

use App\Domain\Courses\CourseRatingStatus;
use App\Domain\Imports\ImportStatus;
use App\Integrations\Ai\ScorecardPhotoReader;
use App\Integrations\Sentry\RetriedJobExceptions;
use App\Jobs\LookUpCourseRating;
use App\Jobs\ReadScorecardImport;
use App\Models\AiUsage;
use App\Models\CourseRating;
use App\Models\Import;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use Sentry\HttpClient\HttpClientInterface;
use Sentry\HttpClient\Request;
use Sentry\HttpClient\Response;
use Sentry\Options;
use Sentry\State\HubInterface;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Tests\Support\Sentry\SentryEventRecorder;

beforeEach(function () {
    config([
        'logging.default' => 'capture',
        'logging.channels.capture' => ['driver' => 'monolog', 'handler' => TestHandler::class],
    ]);
    Route::middleware('web')->get('/unexpected-probe', function (): never {
        throw new RuntimeException('Key (name)=(Manu) already exists');
    });
});

/**
 * @return list<string> the messages written to the log so far
 */
function loggedMessages(): array
{
    /** @var TestHandler $handler */
    $handler = Log::channel('capture')->getLogger()->getHandlers()[0];

    return array_map(fn (LogRecord $record): string => $record->message, $handler->getRecords());
}

/**
 * The real client of the SDK under the configuration of the moment, on an HTTP client that counts
 * what would leave the application.
 */
function sentryHttpClientSpy(): object
{
    $spy = new class implements HttpClientInterface
    {
        public int $requests = 0;

        public function sendRequest(Request $request, Options $options): Response
        {
            $this->requests++;

            return new Response(200, [], '');
        }
    };
    config(['sentry.http_client' => $spy]);
    app()->forgetInstance(HubInterface::class);
    app()->make(HubInterface::class);

    return $spy;
}

test('an unexpected exception on a page sends one event and is logged with its message', function () {
    $recorder = SentryEventRecorder::install();

    $this->withPasscodeSession()->get('/unexpected-probe')->assertStatus(500);

    expect($recorder->events())->toHaveCount(1)
        ->and($recorder->payload()['exception']['values'][0]['type'])->toBe('RuntimeException')
        ->and(loggedMessages())->toBe(['Key (name)=(Manu) already exists']);
});

test('a page that does not exist sends no event', function () {
    $recorder = SentryEventRecorder::install();

    $this->withPasscodeSession()->get('/unknown-page')->assertNotFound();

    expect($recorder->events())->toBe([]);
});

test('a request without session refused with 401 sends no event', function () {
    $recorder = SentryEventRecorder::install();

    $this->postJson('/rounds')->assertUnauthorized();

    expect($recorder->events())->toBe([]);
});

test('a page read without session redirected to /login sends no event', function () {
    $recorder = SentryEventRecorder::install();

    $this->get('/rounds')->assertRedirect('/login');

    expect($recorder->events())->toBe([]);
});

test('without a DSN an exception answers 500, is logged and sends no request', function () {
    $spy = sentryHttpClientSpy();

    $this->withPasscodeSession()->get('/unexpected-probe')->assertStatus(500);

    expect($spy->requests)->toBe(0)
        ->and(loggedMessages())->toBe(['Key (name)=(Manu) already exists']);
});

test('with a DSN the same exception sends a request through the same HTTP client', function () {
    config(['sentry.dsn' => SentryEventRecorder::DSN]);
    $spy = sentryHttpClientSpy();

    $this->withPasscodeSession()->get('/unexpected-probe')->assertStatus(500);

    expect($spy->requests)->toBe(1);
});

test('a request that fails on a stopped database answers 500 and sends exactly one event', function () {
    $recorder = SentryEventRecorder::install();
    // Sessions live in the database in production: the session lookup is the first query to fail.
    config([
        'database.connections.unreachable' => [...config('database.connections.pgsql'), 'host' => 'pgsql.invalid'],
        'database.default' => 'unreachable',
        'session.driver' => 'database',
    ]);

    try {
        $response = $this->get('/login');
    } finally {
        // RefreshDatabase rolls back the default connection of the moment the test ends.
        config(['database.default' => 'pgsql']);
    }

    $response->assertStatus(500);
    expect($recorder->events())->toHaveCount(1)
        ->and(array_column($recorder->payload()['exception']['values'], 'type'))->toBe([
            'PDOException',
            'Illuminate\Database\QueryException',
        ])
        ->and(loggedMessages())->toHaveCount(1)
        ->and(loggedMessages()[0])->toContain('select * from "sessions"');
});

/**
 * An import with its photo, its reading queued on the database connection.
 */
function queueImportReading(): Import
{
    Storage::fake('photos');
    config(['ai.providers.anthropic.key' => 'test-key', 'queue.default' => 'database']);
    $import = Import::factory()->withPhoto()->create();
    ReadScorecardImport::dispatch($import->id);

    return $import;
}

/**
 * Runs the next job on the real worker, then moves past the 30 s backoff of a job released.
 */
function workNextJob(): void
{
    test()->artisan('queue:work', ['--once' => true])->assertSuccessful();
    test()->travel(31)->seconds();
}

test('a network error then a draft sends no event and leaves the error in the log', function () {
    $calls = 0;
    ScorecardPhotoReader::fake(function () use (&$calls) {
        if (++$calls === 1) {
            throw ProviderConnectionException::forProvider('anthropic');
        }

        return billedAnswer(referenceReading());
    });
    $recorder = SentryEventRecorder::install();
    $import = queueImportReading();

    workNextJob();
    workNextJob();

    expect($calls)->toBe(2)
        ->and($import->fresh()->status)->toBe(ImportStatus::Ready)
        ->and($recorder->events())->toBe([])
        ->and(loggedMessages())->toBe(['Could not connect to AI provider [anthropic].']);
});

test('two network errors send exactly one event, with the type, and fail the import', function () {
    ScorecardPhotoReader::fake(fn () => throw ProviderConnectionException::forProvider('anthropic'));
    $recorder = SentryEventRecorder::install();
    $import = queueImportReading();

    workNextJob();

    expect($recorder->events())->toBe([]);

    workNextJob();

    expect($recorder->events())->toHaveCount(1)
        ->and($recorder->payload()['exception']['values'][0]['type'])->toBe('Laravel\Ai\Exceptions\ProviderConnectionException')
        ->and($import->fresh()->status)->toBe(ImportStatus::Failed)
        ->and($import->fresh()->failure)->toBe('Lecture IA indisponible')
        ->and(loggedMessages())->toBe([
            'Could not connect to AI provider [anthropic].',
            'Could not connect to AI provider [anthropic].',
        ]);
});

test('a lookup that SGT never answers sends exactly one event and ends on SGT injoignable', function () {
    config(['golf.sgt.lookup' => 'on']);
    Http::fake(['simulatorgolftour.com/courses' => Http::failedConnection()]);
    createPlayersOnTest('Gab', 'Manu');
    // Made before the round: CreateRound finds a row for the pair and queues nothing.
    $rating = CourseRating::factory()->pending()->create(['course_name' => 'BETHPAGE BLACK', 'tees' => 'Red']);
    createRoundCard(referenceCardData($this->gab->id, $this->manu->id));
    $recorder = SentryEventRecorder::install();
    config(['queue.default' => 'database']);
    LookUpCourseRating::dispatch($rating->id);

    workNextJob();

    expect($recorder->events())->toBe([])
        ->and($rating->refresh()->status)->toBe(CourseRatingStatus::Pending);

    workNextJob();

    Http::assertSentCount(2);
    expect($recorder->events())->toHaveCount(1)
        ->and(array_column($recorder->payload()['exception']['values'], 'type'))->toBe([
            'GuzzleHttp\Exception\ConnectException',
            'Illuminate\Http\Client\ConnectionException',
            'App\Integrations\Sgt\SgtUnavailable',
        ])
        ->and($rating->refresh()->status)->toBe(CourseRatingStatus::Failed)
        ->and($rating->note)->toBe('SGT injoignable');
});

test('a lookup that ends on a manual entry sends no event', function () {
    config(['golf.sgt.lookup' => 'on', 'queue.default' => 'database']);
    // Without any round on the pair: the job stops there, with no call to SGT.
    $rating = CourseRating::factory()->pending()->create(['course_name' => 'BETHPAGE BLACK', 'tees' => 'Red']);
    $recorder = SentryEventRecorder::install();
    LookUpCourseRating::dispatch($rating->id);

    workNextJob();

    expect($rating->refresh()->status)->toBe(CourseRatingStatus::Manual)
        ->and($rating->note)->toBe('Aucun round sur ce parcours')
        ->and($recorder->events())->toBe([]);
});

test('a retried exception is held no longer than the worker holds it', function () {
    $retried = app(RetriedJobExceptions::class);
    $exception = new RuntimeException('Boom');
    $reference = WeakReference::create($exception);

    $retried->remember($exception);

    expect($retried->has($exception))->toBeTrue()
        ->and($retried->has(new RuntimeException('Boom')))->toBeFalse();

    unset($exception);

    expect($reference->get())->toBeNull();
});

/**
 * The ceiling of the month is reached (spec « Plafond de dépense mensuel »).
 */
function reachMonthlyAiBudget(): void
{
    test()->travelTo('2026-10-20 15:00:00');
    AiUsage::factory()->inMonth('2026-10')->create(['cost_micro_usd' => 4960000]);
}

/**
 * The only event sent is the alert of this reason, and the import shows the same reason.
 */
function expectImportFailureAlert(SentryEventRecorder $recorder, Import $import, string $reason): void
{
    expect($recorder->events())->toHaveCount(1)
        ->and($recorder->payload()['message'])->toBe('Import en échec : '.$reason)
        ->and($recorder->payload()['fingerprint'])->toBe(['import-failed', $reason])
        ->and($recorder->payload()['level'])->toBe('error')
        ->and($import->fresh()->status)->toBe(ImportStatus::Failed)
        ->and($import->fresh()->failure)->toBe($reason);
}

test('an answer of the AI out of shape sends one alert with its reason', function () {
    ScorecardPhotoReader::fake([billedAnswer([])]);
    $recorder = SentryEventRecorder::install();
    $import = queueImportReading();

    workNextJob();

    expectImportFailureAlert($recorder, $import, 'Lecture IA indisponible');
});

test('the budget of the month reached sends one alert with its reason', function () {
    reachMonthlyAiBudget();
    $recorder = SentryEventRecorder::install();
    $import = queueImportReading();

    workNextJob();

    expectImportFailureAlert($recorder, $import, 'Budget IA du mois atteint');
});

test('a reading disabled when the worker takes the import sends one alert with its reason', function () {
    $recorder = SentryEventRecorder::install();
    $import = queueImportReading();
    config(['ai.providers.anthropic.key' => '']);

    workNextJob();

    expectImportFailureAlert($recorder, $import, 'Lecture des photos désactivée : aucune clé d’IA n’est configurée.');
});

test('two imports over the budget send two alerts under one fingerprint', function () {
    reachMonthlyAiBudget();
    $recorder = SentryEventRecorder::install();
    queueImportReading();
    queueImportReading();

    workNextJob();
    workNextJob();

    expect($recorder->events())->toHaveCount(2)
        ->and($recorder->payload(0)['fingerprint'])->toBe(['import-failed', 'Budget IA du mois atteint'])
        ->and($recorder->payload(1)['fingerprint'])->toBe(['import-failed', 'Budget IA du mois atteint']);
});

/**
 * The import over the budget ends failed and the job ends done: off the queue, not failed, and
 * nothing in the log. The event held proves that the transport did run.
 */
function expectBudgetFailureSurvivesTransport(Closure $transportOutcome): void
{
    reachMonthlyAiBudget();
    $recorder = SentryEventRecorder::install($transportOutcome);
    $import = queueImportReading();

    workNextJob();

    expect($recorder->events())->toHaveCount(1)
        ->and($import->fresh()->status)->toBe(ImportStatus::Failed)
        ->and($import->fresh()->failure)->toBe('Budget IA du mois atteint')
        ->and(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0)
        ->and(loggedMessages())->toBe([]);
}

test('a transport that fails leaves the import failed and the job done', function () {
    expectBudgetFailureSurvivesTransport(fn (): Result => new Result(ResultStatus::failed()));
});

test('a transport that throws leaves the import failed and the job done', function () {
    expectBudgetFailureSurvivesTransport(fn (): never => throw new RuntimeException('Sentry unreachable'));
});
