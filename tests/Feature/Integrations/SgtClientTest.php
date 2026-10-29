<?php

/*
 * Spec course-ratings « Liste Simulator Golf Tour gardée 24 heures », « Simulator Golf Tour
 * injoignable », « Données envoyées pour une recherche »; design D3, Q12, Q13. Http::fake serves
 * the copies of tests/fixtures/sgt: no test reaches Simulator Golf Tour.
 *
 * Mutations that turn these tests red:
 * - the list kept 1 h instead of 24 h: the second call 23 h later downloads it again;
 * - the cache filled before the page is read (the HTML kept, parsed afterwards): the page
 *   without list is kept and the next call does not ask again;
 * - a delay of 30 s instead of 20 s: the timeout option of the request is 30;
 * - the courses kept as SgtCourse objects: the database store, which unserializes no class,
 *   gives them back as incomplete objects;
 * - the HTTP status not checked (throw() removed): the 404 image comes back as bytes.
 */

use App\Domain\Courses\Sgt\SgtCourse;
use App\Integrations\Sgt\SgtClient;
use App\Integrations\Sgt\SgtUnavailable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function sgtFixture(string $name): string
{
    return (string) file_get_contents(base_path("tests/fixtures/sgt/{$name}"));
}

/**
 * Serves the courses page and the scorecard of course 30 as SGT does.
 */
function fakeSgt(): void
{
    Http::fake([
        'simulatorgolftour.com/courses' => Http::response(sgtFixture('courses.html')),
        'simulatorgolftour.com/public/assets/courseImages/scorecards/scorecard_30.jpg' => Http::response(sgtFixture('scorecard_30.jpg'), 200, ['Content-Type' => 'image/jpeg']),
    ]);
}

test('the list is asked to SGT with the user agent of the application and bounded delays', function () {
    $options = [];
    Http::fake(function (Request $request, array $requestOptions) use (&$options) {
        $options = $requestOptions;

        return Http::response(sgtFixture('courses.html'));
    });

    $courses = app(SgtClient::class)->courses();

    expect($courses)->toHaveCount(14)
        ->and([$options['timeout'], $options['connect_timeout']])->toBe([20, 10]);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://simulatorgolftour.com/courses'
        && $request->header('User-Agent') === ['golf-handicaps-laravel (+https://github.com/emmpaul/golf-handicaps-laravel)']);
});

test('the list is kept 24 hours', function () {
    fakeSgt();
    $client = app(SgtClient::class);

    $client->courses();
    $this->travel(23)->hours();
    $client->courses();
    Http::assertSentCount(1);

    $this->travel(2)->hours();
    $client->courses();
    Http::assertSentCount(2);
});

test('the list kept in the database store is read back as courses', function () {
    config(['cache.default' => 'database']);
    fakeSgt();

    app(SgtClient::class)->courses();
    $courses = app(SgtClient::class)->courses();

    Http::assertSentCount(1);
    expect($courses[2])->toEqual(new SgtCourse(30, 'Bethpage Black', 'Farmingdale, NY', 71));
});

test('an unusable answer of SGT is not kept', function (Closure $firstAnswer) {
    Http::fake(['simulatorgolftour.com/courses' => Http::sequence()
        ->pushResponse($firstAnswer())
        ->push(sgtFixture('courses.html'))]);
    $client = app(SgtClient::class);

    expect(fn () => $client->courses())->toThrow(SgtUnavailable::class)
        ->and($client->courses())->toHaveCount(14);
    Http::assertSentCount(2);
})->with([
    'HTTP 503' => [fn () => Http::response('Service Unavailable', 503)],
    'page without list' => [fn () => Http::response('<html><body>Maintenance</body></html>')],
]);

test('SGT out of reach is unavailable', function () {
    Http::fake(['simulatorgolftour.com/*' => Http::failedConnection('cURL error 28: Operation timed out')]);

    expect(fn () => app(SgtClient::class)->courses())->toThrow(SgtUnavailable::class);
});

test('the scorecard of a course comes back as served', function () {
    fakeSgt();

    $bytes = app(SgtClient::class)->scorecard(30);

    expect(strlen($bytes))->toBe(103915)
        ->and($bytes === sgtFixture('scorecard_30.jpg'))->toBeTrue();
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://simulatorgolftour.com/public/assets/courseImages/scorecards/scorecard_30.jpg'
        && $request->header('User-Agent') === ['golf-handicaps-laravel (+https://github.com/emmpaul/golf-handicaps-laravel)']);
});

test('a missing scorecard is unavailable', function () {
    Http::fake(['simulatorgolftour.com/*' => Http::response('Not Found', 404)]);

    expect(fn () => app(SgtClient::class)->scorecard(30))->toThrow(SgtUnavailable::class);
});
