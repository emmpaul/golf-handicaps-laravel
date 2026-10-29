<?php

/*
 * The lookup job, one case per outcome (spec course-ratings « Correspondance sur la liste
 * Simulator Golf Tour », « Proposition lue sur la carte SGT », « Lecture de la carte sous le
 * budget IA », « Données envoyées pour une recherche », « Simulator Golf Tour injoignable »,
 * « Recherche désactivée »; spec worker-health « Recherche de rating bornée dans le temps »;
 * design D5, D11). SGT_LOOKUP is set to on, Http::fake serves the copies of tests/fixtures/sgt and
 * the agent is faked: no test reaches Simulator Golf Tour nor Anthropic. The rating row is made
 * before the round: CreateRound then finds a row for the pair and queues nothing.
 *
 * Mutations that turn these tests red:
 * - the usage counted after the check of the bounds (only for a card): the slope of 160 leaves
 *   no ai_usage row;
 * - the budget checked after the call: the lookup at the ceiling prompts the agent;
 * - canSpend called without the cost of the scorecard (the 52 000 of a photo): 40 000 under the
 *   ceiling, the lookup ends on « Budget IA du mois atteint » instead of a proposal;
 * - the guard of the pending status removed (StartCourseRatingLookup): the job run twice on the
 *   same row prompts twice;
 * - failed() that writes nothing: after two 503 the row stays pending;
 * - the proposal applied by the job (rating of the tee written, players recomputed): Gab and
 *   Manu go to 0,5 and 13,0 before any confirmation;
 * - failed() that logs nothing, or without the id of the row: no warning names the row;
 * - $timeout at 420: the bounds of the job are 420 / 2 / 30;
 * - the ConnectionException of SgtClient let through: the delay passed is not SgtUnavailable.
 */

use App\Domain\Courses\CourseRatingStatus;
use App\Integrations\Ai\SgtScorecardReader;
use App\Integrations\Sgt\SgtClient;
use App\Integrations\Sgt\SgtUnavailable;
use App\Jobs\LookUpCourseRating;
use App\Models\AiUsage;
use App\Models\CourseRating;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

const SGT_COURSES_URL = 'https://simulatorgolftour.com/courses';
const SGT_SCORECARDS_URL = 'https://simulatorgolftour.com/public/assets/courseImages/scorecards/';

/**
 * Serves the courses page and, for any course, the scorecard of Bethpage Black.
 */
function fakeSgtLookup(): void
{
    Http::fake([
        'simulatorgolftour.com/courses' => Http::response(sgtFixture('courses.html')),
        'simulatorgolftour.com/public/assets/courseImages/scorecards/*' => Http::response(sgtFixture('scorecard_30.jpg'), 200, ['Content-Type' => 'image/jpeg']),
    ]);
}

/**
 * A pending row for the pair, then the reference card saved on it, par 71 or 72 (a par 5 on
 * hole 1). Gab and Manu play it.
 */
function pendingLookupOn(string $courseName, string $tees, int $par = 71, array $attributes = []): CourseRating
{
    $rating = CourseRating::factory()->pending()->create(['course_name' => $courseName, 'tees' => $tees, ...$attributes]);
    $card = referenceCardData(test()->gab->id, test()->manu->id);
    $card['courseName'] = $courseName;
    $card['tees'] = $tees;
    if ($par === 72) {
        $card['holes'][0]['par'] = 5;
    }
    createRoundCard($card);

    return $rating;
}

function runLookup(CourseRating $rating): void
{
    app()->call([new LookUpCourseRating($rating->id), 'handle']);
}

/**
 * [status, note, sgt_course_id, sgt_course_name, sgt_course_location, proposed_tee, rating_t].
 *
 * @return list<mixed>
 */
function lookupOutcomeOf(CourseRating $rating): array
{
    $rating->refresh();

    return [
        $rating->status, $rating->note, $rating->sgt_course_id, $rating->sgt_course_name,
        $rating->sgt_course_location, $rating->proposed_tee, $rating->rating_t,
    ];
}

/**
 * The tees read on the row as [name, ratingT, slope].
 *
 * @return list<list<mixed>>
 */
function teesReadOf(CourseRating $rating): array
{
    return array_map(fn (array $tee): array => [$tee['name'], $tee['ratingT'], $tee['slope']], $rating->refresh()->tees_read ?? []);
}

/**
 * The URLs asked to Simulator Golf Tour, in order.
 *
 * @return list<string>
 */
function sgtUrlsAsked(): array
{
    return Http::recorded()->map(fn (array $pair): string => $pair[0]->url())->values()->all();
}

/**
 * The rows of ai_usage as [model, input_tokens, output_tokens, cost_micro_usd].
 *
 * @return list<list<mixed>>
 */
function lookupUsageRows(): array
{
    return AiUsage::query()->orderBy('created_at')->get()
        ->map(fn (AiUsage $usage): array => [$usage->model, $usage->input_tokens, $usage->output_tokens, $usage->cost_micro_usd])
        ->values()->all();
}

/**
 * The reading of the Bethpage Black scorecard, with a par or tees of its own.
 *
 * @param  array<string, mixed>  $changes
 */
function sgtReadingWith(array $changes): array
{
    return [...bethpageSgtReading(), ...$changes];
}

beforeEach(function () {
    config(['golf.sgt.lookup' => 'on', 'ai.providers.anthropic.key' => 'test-key']);
    $this->travelTo(CarbonImmutable::parse('2026-10-20 15:00:00', 'UTC'));
    createPlayersOnTest('Gab', 'Manu');
});

test('Bethpage Black / Red gets the proposal of the tee Red read on the SGT card 30', function () {
    fakeSgtLookup();
    SgtScorecardReader::fake([billedSgtReading(bethpageSgtReading())]);
    $rating = pendingLookupOn('BETHPAGE BLACK', 'Red');

    runLookup($rating);

    expect(lookupOutcomeOf($rating))->toBe([CourseRatingStatus::Proposed, null, 30, 'Bethpage Black', 'Farmingdale, NY', 'Red', null])
        // The index does not change: Gab and Manu stay at 1,0 and 17,0, on the par and 113.
        ->and(statsByDateOf($this->gab, ['differential_t', 'index_after_t']))->toBe([[10, 10]])
        ->and(statsByDateOf($this->manu, ['differential_t', 'index_after_t']))->toBe([[170, 170]])
        ->and(teesReadOf($rating))->toBe([['Blue', 775, 155], ['White', 742, 148], ['Red', 714, 144], ['Junior', 710, 120], ['PAR3', 540, 120]])
        ->and(lookupUsageRows())->toBe([['claude-sonnet-5-5', 1500, 300, 6000]])
        // Nothing of the group goes to SGT: the whole list, then the card by its id.
        ->and(sgtUrlsAsked())->toBe([SGT_COURSES_URL, SGT_SCORECARDS_URL.'scorecard_30.jpg']);
});

test('THE SANCTUARY / White at par 72 retains the SGT course 477', function () {
    fakeSgtLookup();
    SgtScorecardReader::fake([billedSgtReading(sgtReadingWith(['par' => 72]))]);
    $rating = pendingLookupOn('THE SANCTUARY', 'White', 72);

    runLookup($rating);

    expect(lookupOutcomeOf($rating))->toBe([CourseRatingStatus::Proposed, null, 477, 'Sanctuary', 'Sedalia, CO', 'White', null])
        ->and(sgtUrlsAsked())->toBe([SGT_COURSES_URL, SGT_SCORECARDS_URL.'scorecard_477.jpg']);
});

test('SANCTUARY COVE / Blue offers the SGT courses 477 and 1951 without calling the AI', function () {
    fakeSgtLookup();
    $rating = pendingLookupOn('SANCTUARY COVE', 'Blue', 72);

    runLookup($rating);

    expect(lookupOutcomeOf($rating))->toBe([CourseRatingStatus::ChooseCourse, null, null, null, null, null, null])
        ->and(array_column($rating->candidates ?? [], 'id'))->toBe([477, 1951])
        ->and(sgtUrlsAsked())->toBe([SGT_COURSES_URL]);
    SgtScorecardReader::assertNeverPrompted();
});

test('CALA NOVA / White is not found on SGT', function () {
    fakeSgtLookup();
    $rating = pendingLookupOn('CALA NOVA', 'White', 72);

    runLookup($rating);

    expect(lookupOutcomeOf($rating))->toBe([CourseRatingStatus::NotFound, null, null, null, null, null, null]);
    SgtScorecardReader::assertNeverPrompted();
});

test('tees absent from the card leave the tees read to choose from', function () {
    fakeSgtLookup();
    SgtScorecardReader::fake([billedSgtReading(bethpageSgtReading())]);
    $rating = pendingLookupOn('BETHPAGE BLACK', 'Gold');

    runLookup($rating);

    expect(lookupOutcomeOf($rating))->toBe([CourseRatingStatus::ChooseTee, null, 30, 'Bethpage Black', 'Farmingdale, NY', null, null])
        ->and(count(teesReadOf($rating)))->toBe(5);
});

test('tees with a distance propose the tee of their name', function () {
    fakeSgtLookup();
    SgtScorecardReader::fake([billedSgtReading(bethpageSgtReading())]);
    $rating = pendingLookupOn('BETHPAGE BLACK', 'White 6023yd');

    runLookup($rating);

    expect(lookupOutcomeOf($rating))->toBe([CourseRatingStatus::Proposed, null, 30, 'Bethpage Black', 'Farmingdale, NY', 'White', null]);
});

test('a par read different from the round leaves a manual entry with the SGT course', function () {
    fakeSgtLookup();
    SgtScorecardReader::fake([billedSgtReading(sgtReadingWith(['par' => 72]))]);
    $rating = pendingLookupOn('BETHPAGE BLACK', 'Red');

    runLookup($rating);

    expect(lookupOutcomeOf($rating))->toBe([CourseRatingStatus::Manual, 'Par de la carte SGT différent', 30, 'Bethpage Black', 'Farmingdale, NY', null, null])
        ->and($rating->tees_read)->toBeNull();
});

test('an unusable reading leaves a manual entry and its usage is counted', function (array $reading) {
    fakeSgtLookup();
    SgtScorecardReader::fake([billedSgtReading($reading)]);
    $rating = pendingLookupOn('BETHPAGE BLACK', 'Red');

    runLookup($rating);

    expect(lookupOutcomeOf($rating))->toBe([CourseRatingStatus::Manual, 'Lecture IA impossible', 30, 'Bethpage Black', 'Farmingdale, NY', null, null])
        ->and(lookupUsageRows())->toBe([['claude-sonnet-5-5', 1500, 300, 6000]]);
})->with([
    'slope of 160' => [['found' => true, 'par' => 71, 'tees' => [['name' => 'Red', 'rating' => 71.4, 'slope' => 160]]]],
    // Decision Q17.
    'found without any tee' => [['found' => true, 'par' => 71, 'tees' => []]],
]);

test('without key the lookup keeps the SGT course, calls no AI and asks no scorecard', function () {
    config(['ai.providers.anthropic.key' => null]);
    fakeSgtLookup();
    $rating = pendingLookupOn('BETHPAGE BLACK', 'Red');

    runLookup($rating);

    expect(lookupOutcomeOf($rating))->toBe([CourseRatingStatus::Manual, 'Lecture IA désactivée', 30, 'Bethpage Black', 'Farmingdale, NY', null, null])
        ->and(sgtUrlsAsked())->toBe([SGT_COURSES_URL]);
    SgtScorecardReader::assertNeverPrompted();
});

test('at the ceiling of the month the lookup calls no AI', function () {
    AiUsage::factory()->inMonth('2026-10')->create(['cost_micro_usd' => 4990000]);
    fakeSgtLookup();
    $rating = pendingLookupOn('BETHPAGE BLACK', 'Red');

    runLookup($rating);

    expect(lookupOutcomeOf($rating))->toBe([CourseRatingStatus::Manual, 'Budget IA du mois atteint', 30, 'Bethpage Black', 'Farmingdale, NY', null, null])
        ->and(sgtUrlsAsked())->toBe([SGT_COURSES_URL])
        ->and(AiUsage::query()->count())->toBe(1);
    SgtScorecardReader::assertNeverPrompted();
});

test('40 000 micro-dollars under the ceiling the lookup still reads the scorecard', function () {
    // 4 960 000 + 19 000 (the scorecard) fits under 5 $; 4 960 000 + 52 000 (a photo) does not.
    AiUsage::factory()->inMonth('2026-10')->create(['cost_micro_usd' => 4960000]);
    fakeSgtLookup();
    SgtScorecardReader::fake([billedSgtReading(bethpageSgtReading())]);
    $rating = pendingLookupOn('BETHPAGE BLACK', 'Red');

    runLookup($rating);

    expect($rating->refresh()->status)->toBe(CourseRatingStatus::Proposed)
        ->and(AiUsage::query()->orderBy('cost_micro_usd')->pluck('cost_micro_usd')->all())->toBe([6000, 4960000]);
    SgtScorecardReader::assertPromptedTimes(1);
});

test('with SGT_LOOKUP off the lookup asks nothing to SGT', function () {
    config(['golf.sgt.lookup' => 'off']);
    Http::fake();
    $rating = pendingLookupOn('BETHPAGE BLACK', 'Red');

    runLookup($rating);

    expect(lookupOutcomeOf($rating))->toBe([CourseRatingStatus::Manual, 'Recherche SGT désactivée', null, null, null, null, null]);
    Http::assertNothingSent();
    SgtScorecardReader::assertNeverPrompted();
});

test('a pair without any round left asks nothing to SGT', function () {
    Http::fake();
    $rating = CourseRating::factory()->pending()->create(['course_name' => 'BETHPAGE BLACK', 'tees' => 'Red']);

    runLookup($rating);

    expect(lookupOutcomeOf($rating))->toBe([CourseRatingStatus::Manual, 'Aucun round sur ce parcours', null, null, null, null, null]);
    Http::assertNothingSent();
});

test('a candidate already chosen skips the list and reads its own scorecard', function () {
    fakeSgtLookup();
    SgtScorecardReader::fake([billedSgtReading(sgtReadingWith(['par' => 72]))]);
    $rating = pendingLookupOn('SANCTUARY COVE', 'Blue', 72, [
        'sgt_course_id' => 1951,
        'sgt_course_name' => 'The Pines Sanctuary Cove',
        'sgt_course_location' => 'Hope Island, Queensland, Australia',
    ]);

    runLookup($rating);

    expect(lookupOutcomeOf($rating))->toBe([CourseRatingStatus::Proposed, null, 1951, 'The Pines Sanctuary Cove', 'Hope Island, Queensland, Australia', 'Blue', null])
        ->and(sgtUrlsAsked())->toBe([SGT_SCORECARDS_URL.'scorecard_1951.jpg']);
});

test('a 503 then the page: the second attempt ends as if nothing had happened', function () {
    Http::fake([
        'simulatorgolftour.com/courses' => Http::sequence()->push('', 503)->push(sgtFixture('courses.html')),
        'simulatorgolftour.com/public/assets/courseImages/scorecards/*' => Http::response(sgtFixture('scorecard_30.jpg')),
    ]);
    SgtScorecardReader::fake([billedSgtReading(bethpageSgtReading())]);
    $rating = pendingLookupOn('BETHPAGE BLACK', 'Red');

    expect(fn () => runLookup($rating))->toThrow(SgtUnavailable::class);
    expect($rating->refresh()->status)->toBe(CourseRatingStatus::Pending);

    runLookup($rating);

    expect(lookupOutcomeOf($rating))->toBe([CourseRatingStatus::Proposed, null, 30, 'Bethpage Black', 'Farmingdale, NY', 'Red', null]);
});

test('through the queue, two 503 end the lookup in failure', function () {
    Http::fake(['simulatorgolftour.com/courses' => Http::response('', 503)]);
    $rating = pendingLookupOn('BETHPAGE BLACK', 'Red');
    config(['queue.default' => 'database']);
    LookUpCourseRating::dispatch($rating->id);

    $this->artisan('queue:work', ['--stop-when-empty' => true, '--sleep' => 0])->assertSuccessful();
    expect($rating->refresh()->status)->toBe(CourseRatingStatus::Pending);

    // Released for its 30 s backoff.
    $this->travel(31)->seconds();
    $this->artisan('queue:work', ['--stop-when-empty' => true, '--sleep' => 0])->assertSuccessful();

    expect(lookupOutcomeOf($rating))->toBe([CourseRatingStatus::Failed, 'SGT injoignable', null, null, null, null, null]);
    Http::assertSentCount(2);
});

test('the failure of the lookup is logged with its row and its exception', function () {
    Log::spy();
    $rating = pendingLookupOn('BETHPAGE BLACK', 'Red');
    $exception = new SgtUnavailable('SGT unreachable: HTTP request returned status code 503');

    (new LookUpCourseRating($rating->id))->failed($exception);

    Log::shouldHaveReceived('warning')->once()->with('SGT rating lookup failed', [
        'course_rating_id' => $rating->id,
        'exception' => $exception,
    ]);
    expect($rating->refresh()->status)->toBe(CourseRatingStatus::Failed);
});

test('the job run twice on the same row calls the AI once', function () {
    fakeSgtLookup();
    SgtScorecardReader::fake([billedSgtReading(bethpageSgtReading()), billedSgtReading(bethpageSgtReading())]);
    $rating = pendingLookupOn('BETHPAGE BLACK', 'Red');

    runLookup($rating);
    runLookup($rating);

    SgtScorecardReader::assertPromptedTimes(1);
    expect(AiUsage::query()->count())->toBe(1);
});

// Spec worker-health « Recherche de rating bornée dans le temps »; task 5.3.
test('the job is bounded to 120 s, tried twice 30 s apart', function () {
    $job = new LookUpCourseRating('0199f0c8-0000-7000-8000-000000000000');

    expect([$job->timeout, $job->tries, $job->backoff])->toBe([120, 2, 30]);
});

test('the list, the scorecard and the reading at their delays end within the 120 s of the job', function () {
    // A lookup sends at most the list and the scorecard to SGT, then one reading to the AI: past
    // the job timeout, a reading billed but not yet counted would be lost and billed again by
    // the retry. Mutation: the reading bounded to 90 s, as the photo: 20 + 20 + 90 = 130.
    $job = new LookUpCourseRating('0199f0c8-0000-7000-8000-000000000000');
    $delays = [SgtClient::TIMEOUT_SECONDS, SgtScorecardReader::TIMEOUT_SECONDS, $job->timeout];

    expect($delays)->toBe([20, 60, 120])
        ->and(2 * $delays[0] + $delays[1])->toBeLessThan($delays[2]);
});

test('a request to SGT past its delay ends the attempt as SGT unavailable and leaves the row pending', function () {
    Http::fake(fn (Request $request) => throw new ConnectionException('cURL error 28: Operation timed out after 20000 milliseconds'));
    $rating = pendingLookupOn('BETHPAGE BLACK', 'Red');

    expect(fn () => runLookup($rating))->toThrow(SgtUnavailable::class);

    expect($rating->refresh()->status)->toBe(CourseRatingStatus::Pending);
});
