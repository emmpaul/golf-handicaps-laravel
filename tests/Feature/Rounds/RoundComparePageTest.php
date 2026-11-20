<?php

/*
 * Spec virtual-match, « Page Partie virtuelle protégée et sans écriture », « Choix du parcours
 * de la partie virtuelle », « Choix gardé dans l'adresse »; design D4.
 *
 * Mutations that turn these tests red:
 * - the route rounds.compare declared after rounds/{round} without whereUuid, or declared with
 *   withoutMiddleware(RequirePasscode::class): /rounds/compare answers without a session;
 * - a route /rounds/compare/{any}: /rounds/compare/xyz no longer answers 404;
 * - the last known course taken instead of the first: the page opens on PAYNES VALLEY / Blue;
 * - the picks kept on an unknown course: Gab of BETHPAGE BLACK is picked on the first course;
 * - CompareRoundsRequest that validates the picks (`regex`, `uuid`): a malformed pick answers
 *   302 or 422 instead of the page;
 * - the picks sorted by player or by round: Sean, picked first, comes after Gab;
 * - the requested course kept as typed instead of the known one: « Bethpage Black / red » is
 *   sent to the page instead of « BETHPAGE BLACK / Red »;
 * - the page that saves the choice (a row in any table, or an updated one): the snapshot of
 *   the tables differs.
 */

use App\Domain\LegacyImport\TargetTables;
use App\Models\Round;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The reference card on $courseName / $tees on $playedOn, one line per player id in this
 * order, each with Gab's 72 of the reference card.
 *
 * @param  list<string>  $playerIds
 */
function compareRoundCard(string $courseName, string $tees, string $playedOn, array $playerIds): Round
{
    $strokes = referenceCardData(null, null)['lines'][0]['strokes'];
    $card = referenceCardOn($playedOn, array_map(fn (string $playerId): array => [$playerId, $strokes], $playerIds));
    $card['courseName'] = $courseName;
    $card['tees'] = $tees;

    return createRoundCard($card);
}

/**
 * Every row of every table of the domain, by value.
 *
 * @return array<string, list<array<string, mixed>>>
 */
function domainTablesSnapshot(): array
{
    return array_combine(TargetTables::TABLES, array_map(
        fn (string $table): array => DB::table($table)->orderBy('id')->get()->map(fn (stdClass $row): array => (array) $row)->all(),
        TargetTables::TABLES,
    ));
}

test('without a session /rounds/compare redirects to /login without any name', function () {
    createPlayersOnTest('Gab');
    compareRoundCard('BETHPAGE BLACK', 'Red', '2026-11-18', [$this->gab->id]);

    $response = $this->get('/rounds/compare?course=BETHPAGE%20BLACK&tees=Red')->assertRedirect('/login');

    expect($response->getContent())->not->toContain('Gab')
        ->and($response->getContent())->not->toContain('BETHPAGE');
});

describe('with a session', function () {
    beforeEach(function () {
        $this->withPasscodeSession();
        createPlayersOnTest('Gab', 'Manu', 'Sean');
    });

    test('a sub-address of /rounds/compare answers 404', function () {
        $this->get('/rounds/compare/xyz')->assertNotFound();
    });

    test('without parameter the page opens on the first known course without pick', function () {
        compareRoundCard('BETHPAGE BLACK', 'Red', '2026-11-18', [$this->gab->id]);
        // Saved last and played last: the first known course is still BETHPAGE BLACK.
        compareRoundCard('PAYNES VALLEY', 'Blue', '2026-11-19', [$this->manu->id]);

        $this->get('/rounds/compare')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('rounds/compare')
                ->where('courses', [
                    ['courseName' => 'BETHPAGE BLACK', 'tees' => 'Red'],
                    ['courseName' => 'PAYNES VALLEY', 'tees' => 'Blue'],
                ])
                ->where('match.courseName', 'BETHPAGE BLACK')
                ->where('match.tees', 'Red')
                ->where('match.rating', ['rated' => false, 'ratingT' => null, 'slope' => 113])
                ->where('match.picks', [])
                ->where('match.leaderboard', [])
                ->where('match.candidates.0.name', 'Gab'));
    });

    test('a known course is found without case nor surrounding spaces and sent as known', function () {
        $gabRound = compareRoundCard('BETHPAGE BLACK', 'Red', '2026-11-18', [$this->gab->id]);

        $this->get('/rounds/compare?'.http_build_query(['course' => ' Bethpage Black ', 'tees' => 'red', 'picks' => "{$gabRound->id}.{$this->gab->id}"]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('match.courseName', 'BETHPAGE BLACK')
                ->where('match.tees', 'Red')
                ->where('match.picks.0.name', 'Gab'));
    });

    test('malformed picks and picks of another course are ignored without error', function () {
        $gabRound = compareRoundCard('BETHPAGE BLACK', 'Red', '2026-11-18', [$this->gab->id]);
        $paynesRound = compareRoundCard('PAYNES VALLEY', 'Blue', '2026-11-19', [$this->manu->id]);

        $picks = implode(',', [
            'garbage',
            "{$gabRound->id}.12",
            "{$gabRound->id}.{$this->gab->id}.extra",
            "{$paynesRound->id}.{$this->manu->id}",
            '',
            "{$gabRound->id}.{$this->gab->id}",
        ]);

        $this->get('/rounds/compare?'.http_build_query(['course' => 'BETHPAGE BLACK', 'tees' => 'Red', 'picks' => $picks]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('rounds/compare')
                ->where('match.courseName', 'BETHPAGE BLACK')
                ->where('match.picks', [[
                    'roundId' => $gabRound->id,
                    'playerId' => $this->gab->id,
                    'name' => 'Gab',
                    'colorSlot' => 0,
                    'playedOn' => '2026-11-18',
                    'strokes' => [5, 5, 3, 5, 4, 4, 4, 3, 5, 4, 4, 4, 4, 3, 4, 5, 3, 3],
                ]]));
    });

    test('picks of another shape are ignored without error', function (array $query) {
        compareRoundCard('BETHPAGE BLACK', 'Red', '2026-11-18', [$this->gab->id]);

        $this->get('/rounds/compare?'.http_build_query($query))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('match.courseName', 'BETHPAGE BLACK')
                ->where('match.picks', []));
    })->with([
        'picks as a list' => [['course' => 'BETHPAGE BLACK', 'tees' => 'Red', 'picks' => ['a', 'b']]],
        'course as a list' => [['course' => ['BETHPAGE BLACK'], 'tees' => 'Red', 'picks' => 'a.b']],
    ]);

    test('the picks of an unknown course are ignored on the first known course', function () {
        $gabRound = compareRoundCard('BETHPAGE BLACK', 'Red', '2026-11-18', [$this->gab->id]);

        $this->get('/rounds/compare?'.http_build_query(['course' => 'PEBBLE BEACH', 'tees' => 'Red', 'picks' => "{$gabRound->id}.{$this->gab->id}"]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('match.courseName', 'BETHPAGE BLACK')
                ->where('match.tees', 'Red')
                ->where('match.picks', []));
    });

    test('the picks are sent in the order of the address', function () {
        $gabRound = compareRoundCard('BETHPAGE BLACK', 'Red', '2026-11-18', [$this->gab->id]);
        $manuRound = compareRoundCard('BETHPAGE BLACK', 'Red', '2026-11-19', [$this->manu->id, $this->sean->id]);
        $picks = "{$manuRound->id}.{$this->sean->id},{$gabRound->id}.{$this->gab->id},{$manuRound->id}.{$this->manu->id}";

        $response = $this->get('/rounds/compare?'.http_build_query(['course' => 'BETHPAGE BLACK', 'tees' => 'Red', 'picks' => $picks]))->assertOk();

        expect(array_column($response->inertiaProps('match.picks'), 'name'))->toBe(['Sean', 'Gab', 'Manu']);
    });

    test('opening, changing and unfolding the virtual match writes nothing', function () {
        $gabRound = compareRoundCard('BETHPAGE BLACK', 'Red', '2026-11-18', [$this->gab->id]);
        $manuRound = compareRoundCard('BETHPAGE BLACK', 'Red', '2026-11-19', [$this->manu->id, $this->sean->id]);
        $three = "{$gabRound->id}.{$this->gab->id},{$manuRound->id}.{$this->manu->id},{$manuRound->id}.{$this->sean->id}";
        $before = domainTablesSnapshot();

        $this->get('/rounds/compare')->assertOk();
        $this->get('/rounds/compare?'.http_build_query(['course' => 'BETHPAGE BLACK', 'tees' => 'Red', 'picks' => $three]))->assertOk();
        $this->get('/rounds/compare?'.http_build_query(['course' => 'BETHPAGE BLACK', 'tees' => 'Red', 'picks' => "{$manuRound->id}.{$this->manu->id}"]))->assertOk();
        $this->get("/rounds/{$manuRound->id}")->assertOk();

        expect(domainTablesSnapshot())->toBe($before);
    });

    test('without round the list of courses is empty', function () {
        $this->get('/rounds/compare')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('rounds/compare')
                ->where('courses', [])
                ->where('match', null));
    });
});
