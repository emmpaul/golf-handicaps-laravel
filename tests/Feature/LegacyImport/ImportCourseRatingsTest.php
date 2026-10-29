<?php

/*
 * Spec legacy-import « Recherches de rating remises en file »; design D7, D12.
 */

use App\Jobs\LookUpCourseRating;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\LegacySqlite\LegacySqliteFixture;

beforeEach(fn () => Queue::fake());

const PAYNES_CANDIDATES = '[{"id":41,"name":"Payne\'s Valley","location":"Missouri","par":71},{"id":42,"name":"Paynes Valley Short","location":"Missouri","par":27}]';

// Spec « Recherche interrompue ».
// Mutation that turns it red: every row requeued (the choose_course row too).
test('a pending lookup is requeued after the commit, the other rows keep their columns', function () {
    $base = LegacySqliteFixture::referenceRound()
        ->courseRating(1, 'BETHPAGE BLACK', 'Red', 1792624190600)
        ->courseRating(2, 'PAYNES VALLEY', 'Blue', 1792624190600, [
            'status' => 'choose_course',
            'candidates' => PAYNES_CANDIDATES,
            'tees_read' => '["Blue","White"]',
            'proposed_tee' => 'Blue',
            'note' => 'Deux parcours au même nom',
        ]);

    [$exitCode, $lines] = importSqlite($base->path);

    $pending = DB::table('course_ratings')->where('course_name', 'BETHPAGE BLACK')->value('id');
    $choose = DB::table('course_ratings')->where('course_name', 'PAYNES VALLEY')->sole();
    expect($exitCode)->toBe(0)
        ->and($lines)->toContain('Recherches de rating remises en file : 1')
        ->and([$choose->tees, $choose->rating_t, $choose->slope, $choose->source, $choose->status, $choose->sgt_course_id, $choose->sgt_course_name, $choose->sgt_course_location, $choose->proposed_tee, $choose->note])
        ->toBe(['Blue', null, null, null, 'choose_course', null, null, null, 'Blue', 'Deux parcours au même nom'])
        // jsonb orders the keys of an object its own way: compared by value.
        ->and(json_decode($choose->candidates, true))->toEqual([
            ['id' => 41, 'name' => 'Payne\'s Valley', 'location' => 'Missouri', 'par' => 71],
            ['id' => 42, 'name' => 'Paynes Valley Short', 'location' => 'Missouri', 'par' => 27],
        ])
        ->and(json_decode($choose->tees_read, true))->toBe(['Blue', 'White']);
    Queue::assertPushed(LookUpCourseRating::class, 1);
    Queue::assertPushed(LookUpCourseRating::class, fn (LookUpCourseRating $job): bool => $job->courseRatingId === $pending);
});

// D7: candidates go to jsonb as an array, not as a string of JSON.
// Mutation that turns it red: candidates written as text (json_encode of the SQLite string).
test('the candidates are written as a json array', function () {
    $base = LegacySqliteFixture::referenceRound()
        ->courseRating(1, 'PAYNES VALLEY', 'Blue', 1792624190600, ['status' => 'choose_course', 'candidates' => PAYNES_CANDIDATES]);

    importSqlite($base->path);

    expect(DB::selectOne('select jsonb_typeof(candidates) as type from course_ratings')->type)->toBe('array');
});

// Spec « Rating confirmée ». 113 / 144 × (72 − 71,4) = 0,47 → 5 ; 113 / 144 × (88 − 71,4) = 13,03 → 130.
// Mutation that turns it red: the confirmed rating not copied (rating_t null: the statistics are
// recomputed on the par and 113, 10 and 170, and differ from SQLite).
test('the statistics are recomputed on a confirmed rating and compared to SQLite', function () {
    $card = referenceCardData(null, null);
    $base = LegacySqliteFixture::atLevel()
        ->player(1, 'Gab', 1791400000000)
        ->player(2, 'Manu', 1791500000000)
        ->round(1, 'BETHPAGE BLACK', 'Red', '2026-10-17', 1792251843742, $card['holes'], [
            [1, $card['lines'][0]['strokes']],
            [2, $card['lines'][1]['strokes']],
        ])
        ->stats(1, 1, 72, 72, 5, null, 5, true)
        ->stats(1, 2, 88, 88, 130, null, 130, true)
        ->courseRating(1, 'BETHPAGE BLACK', 'Red', 1792624190600, [
            'status' => 'confirmed', 'rating_t' => 714, 'slope' => 144, 'source' => 'sgt',
        ]);

    [$exitCode, $lines] = importSqlite($base->path);

    expect($exitCode)->toBe(0)
        ->and(DB::table('player_round_stats')->orderBy('differential_t')->pluck('differential_t')->all())->toBe([5, 130])
        ->and($lines)->toContain('Statistiques identiques : 2 / 2')
        ->and($lines)->toContain('Recherches de rating remises en file : 0');
    Queue::assertNothingPushed();
});

// D7: candidates that are not JSON fail the import.
// Mutation that turns it red: candidates passed to PostgreSQL undecoded (its refusal names the
// row, not the column).
test('candidates that cannot be read fail the import', function () {
    $base = LegacySqliteFixture::referenceRound()
        ->courseRating(1, 'PAYNES VALLEY', 'Blue', 1792624190600, ['status' => 'choose_course', 'candidates' => '[{"id":41,']);

    [$exitCode, $lines] = importSqlite($base->path);

    expect($exitCode)->toBe(1)
        ->and($lines)->toBe(['Valeur illisible : course_ratings.candidates'])
        ->and(nonEmptyTargetTables())->toBe([]);
});

// D3: the lookups go to the queue after the commit only.
// Mutation that turns it red: the job dispatched before the commit (a failed comparison leaves a
// job in the queue).
test('an import that fails after its ratings queues no lookup', function () {
    $base = LegacySqliteFixture::atLevel()
        ->player(1, 'Gab', 1791400000000)
        ->round(1, 'BETHPAGE BLACK', 'Red', '2026-10-17', 1792251843742, referenceCardData(null, null)['holes'], [[1, strokesOverPar(1)]])
        ->stats(1, 1, 72, 72, 11, null, 11, true)
        ->courseRating(1, 'BETHPAGE BLACK', 'Red', 1792624190600);

    [$exitCode] = importSqlite($base->path);

    expect($exitCode)->toBe(1);
    Queue::assertNothingPushed();
});
