<?php

/*
 * Spec performance-measurement « Mesure en lecture seule », « Code d'accès hors du dépôt » and
 * « Rapport comparable »; design D2. Http::fake() stands in for the measured site: nothing leaves
 * the test. The fake answers carry no transfer statistics, so the timing and size columns of the
 * report show « — »; the arithmetic is covered by tests/Unit/PerfProbeStatisticsTest.php.
 *
 * Mutations that turn these tests red:
 * - a second POST /login, or a protected page measured with a POST: the method list differs;
 * - /courses left out of the addresses, or /import of the Inertia visits: the measured list differs;
 * - the redirect of the POST not checked: the refused code goes on to the protected pages;
 * - the passcode asked even when PERF_PASSCODE is set: « Unexpected question »;
 * - ask() instead of secret(): the hidden question expected by the output mock is never asked;
 * - the X-XSRF-TOKEN header dropped from the POST, or X-Inertia-Version not read from data-page;
 * - the public pages measured with the session cookie: /login carries the cookie;
 * - the report named after the time instead of the date, or written without its runs line;
 * - the Accept-Encoding: gzip header removed: the server answers uncompressed, the size is wrong.
 */

use App\Console\Commands\PerfProbeCommand;
use Illuminate\Console\OutputStyle;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Question\Question;

const PERF_REPORT = 'reports/perf/2026-11-02-golf.emmanuelpaul.com.md';

beforeEach(function () {
    putenv('PERF_PASSCODE=code-de-test');
    $this->travelTo('2026-11-02 15:00:00');
    File::delete(base_path(PERF_REPORT));
});

afterEach(function () {
    putenv('PERF_PASSCODE');
    File::delete(base_path(PERF_REPORT));
});

/**
 * The measured site: /login sets the XSRF and session cookies and holds the Inertia version in
 * data-page; the POST redirects to $loginLocation with a new session cookie; every other page
 * answers 200 with Server-Timing.
 */
function fakeMeasuredSite(string $loginLocation = 'https://golf.emmanuelpaul.com/'): void
{
    Http::fake([
        'golf.emmanuelpaul.com/login' => fn (Request $request) => $request->method() === 'POST'
            ? Http::response('', 302, [
                'Location' => $loginLocation,
                'Set-Cookie' => 'golf_session=session-apres-connexion; path=/; httponly',
            ])
            : Http::response(
                '<html><body><script data-page="app" type="application/json">{"component":"login","props":{},"url":"/login","version":"version-de-test"}</script><div id="app"></div></body></html>',
                200,
                ['Set-Cookie' => ['XSRF-TOKEN=jeton-xsrf-de-test; path=/', 'golf_session=session-avant-connexion; path=/; httponly']],
            ),
        'golf.emmanuelpaul.com/*' => Http::response('<html></html>', 200, [
            'Server-Timing' => 'app;dur=12.5, db;dur=3, db-count;dur=4',
        ]),
    ]);
}

/**
 * Each request sent, as « METHOD path », « METHOD path inertia » for an Inertia visit.
 *
 * @return list<string>
 */
function sentRequests(): array
{
    return Http::recorded()->map(fn (array $pair): string => trim(sprintf(
        '%s %s %s',
        $pair[0]->method(),
        parse_url($pair[0]->url(), PHP_URL_PATH),
        $pair[0]->hasHeader('X-Inertia') ? 'inertia' : '',
    )))->values()->all();
}

test('one POST /login, then GET requests only', function () {
    fakeMeasuredSite();

    $this->artisan('golf:perf-probe', ['url' => 'https://golf.emmanuelpaul.com', '--runs' => 2])->assertSuccessful();

    Http::assertSentCount(34);
    expect(Http::recorded()->map(fn (array $pair): string => $pair[0]->method())->values()->all())
        ->toBe(['GET', 'POST', ...array_fill(0, 32, 'GET')]);
});

test('the eleven addresses are measured as full pages and the five sections as Inertia visits', function () {
    fakeMeasuredSite();

    $this->artisan('golf:perf-probe', ['url' => 'https://golf.emmanuelpaul.com', '--runs' => 1])->assertSuccessful();

    expect(sentRequests())->toBe([
        'GET /login',
        'POST /login',
        'GET /robots.txt',
        'GET /up',
        'GET /api/version',
        'GET /login',
        'GET /',
        'GET /handicaps',
        'GET /strokes',
        'GET /rounds',
        'GET /players',
        'GET /courses',
        'GET /import',
        'GET /rounds inertia',
        'GET /handicaps inertia',
        'GET /strokes inertia',
        'GET /players inertia',
        'GET /import inertia',
    ]);
});

test('the login posts the passcode with the XSRF token of the login page', function () {
    fakeMeasuredSite();

    $this->artisan('golf:perf-probe', ['url' => 'https://golf.emmanuelpaul.com', '--runs' => 1])->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://golf.emmanuelpaul.com/login'
        && $request['passcode'] === 'code-de-test'
        && $request->header('X-XSRF-TOKEN') === ['jeton-xsrf-de-test']);
});

test('the protected pages carry the new session, the public ones no cookie, the Inertia visits the version', function () {
    fakeMeasuredSite();

    $this->artisan('golf:perf-probe', ['url' => 'https://golf.emmanuelpaul.com', '--runs' => 1])->assertSuccessful();

    // The cookie header of each page reduced to the session it carries: the jar also sends the
    // XSRF cookie, which the measured pages ignore.
    $measured = Http::recorded()->slice(2)->map(fn (array $pair): array => [
        parse_url($pair[0]->url(), PHP_URL_PATH),
        preg_match('/golf_session=([a-z-]+)/', $pair[0]->header('Cookie')[0] ?? '', $cookie) === 1 ? $cookie[1] : null,
        $pair[0]->header('X-Inertia-Version'),
    ])->values()->all();

    $session = 'session-apres-connexion';
    expect($measured)->toBe([
        ['/robots.txt', null, []],
        ['/up', null, []],
        ['/api/version', null, []],
        ['/login', null, []],
        ['/', $session, []],
        ['/handicaps', $session, []],
        ['/strokes', $session, []],
        ['/rounds', $session, []],
        ['/players', $session, []],
        ['/courses', $session, []],
        ['/import', $session, []],
        ['/rounds', $session, ['version-de-test']],
        ['/handicaps', $session, ['version-de-test']],
        ['/strokes', $session, ['version-de-test']],
        ['/players', $session, ['version-de-test']],
        ['/import', $session, ['version-de-test']],
    ]);
});

test('every measured GET asks for a gzip answer, as a browser does', function () {
    fakeMeasuredSite();

    $this->artisan('golf:perf-probe', ['url' => 'https://golf.emmanuelpaul.com', '--runs' => 2])->assertSuccessful();

    // The size column is the compressed size received: without Accept-Encoding the server
    // answers uncompressed and size_download counts the decoded bytes.
    $measured = Http::recorded()->slice(2)->values();

    expect($measured)->toHaveCount(32)
        ->and($measured->map(fn (array $pair): string => $pair[0]->header('Accept-Encoding')[0] ?? '')->unique()->values()->all())
        ->toBe(['gzip']);
});

test('a refused passcode stops the command before any protected page', function () {
    fakeMeasuredSite(loginLocation: 'https://golf.emmanuelpaul.com/login');

    $this->artisan('golf:perf-probe', ['url' => 'https://golf.emmanuelpaul.com', '--runs' => 1])
        ->expectsOutputToContain("Code d'accès refusé")
        ->assertFailed();

    expect(sentRequests())->toBe(['GET /login', 'POST /login'])
        ->and(base_path(PERF_REPORT))->not->toBeFile();
});

test('with PERF_PASSCODE no question is asked and no secret reaches the output', function () {
    fakeMeasuredSite();

    $this->artisan('golf:perf-probe', ['url' => 'https://golf.emmanuelpaul.com', '--runs' => 1])
        ->doesntExpectOutputToContain('code-de-test')
        ->doesntExpectOutputToContain('session-avant-connexion')
        ->doesntExpectOutputToContain('session-apres-connexion')
        ->doesntExpectOutputToContain('jeton-xsrf-de-test')
        ->assertSuccessful();
});

test('without PERF_PASSCODE the passcode is asked without being shown', function () {
    putenv('PERF_PASSCODE');
    fakeMeasuredSite();

    $output = Mockery::mock(OutputStyle::class.'[askQuestion]', [new ArrayInput([]), new BufferedOutput]);
    $output->shouldReceive('askQuestion')
        ->once()
        ->with(Mockery::on(fn (Question $question): bool => $question->getQuestion() === "Code d'accès" && $question->isHidden()))
        ->andReturn('code-de-test');
    $this->app->bind(OutputStyle::class, fn () => $output);

    $exitCode = Artisan::call('golf:perf-probe', ['url' => 'https://golf.emmanuelpaul.com', '--runs' => 1]);

    expect($exitCode)->toBe(PerfProbeCommand::SUCCESS);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request['passcode'] === 'code-de-test');
});

test('the report is written under the date and host, without any secret', function () {
    fakeMeasuredSite();

    $this->artisan('golf:perf-probe', ['url' => 'https://golf.emmanuelpaul.com', '--runs' => 2])->assertSuccessful();

    expect(base_path(PERF_REPORT))->toBeFile();
    $report = (string) file_get_contents(base_path(PERF_REPORT));

    expect($report)
        ->toContain('Adresse : https://golf.emmanuelpaul.com')
        ->toContain('Date : 2026-11-02')
        ->toContain('Essais par page : 2')
        ->toContain('| `/rounds` | Inertia | 200 | — | — | — | 12.5 | 4 | — |')
        ->not->toContain('code-de-test')
        ->not->toContain('session-avant-connexion')
        ->not->toContain('session-apres-connexion')
        ->not->toContain('jeton-xsrf-de-test');

    preg_match_all('/^\| `([^`]+)` \| (page|Inertia) \|/m', $report, $rows, PREG_SET_ORDER);
    expect(array_map(fn (array $row): string => "{$row[1]} {$row[2]}", $rows))->toBe([
        '/robots.txt page',
        '/up page',
        '/api/version page',
        '/login page',
        '/ page',
        '/handicaps page',
        '/strokes page',
        '/rounds page',
        '/players page',
        '/courses page',
        '/import page',
        '/rounds Inertia',
        '/handicaps Inertia',
        '/strokes Inertia',
        '/players Inertia',
        '/import Inertia',
    ]);
});
