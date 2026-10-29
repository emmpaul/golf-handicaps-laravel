<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\LegacySqlite\LegacySqliteFixture;

beforeEach(fn () => Storage::fake('photos'));

/**
 * A base of two players and the reference card played by both on 2026-10-17 (round 1), whose
 * holes are changed by $changeHoles, with the statistics of D15 (the pars and the strokes stay).
 *
 * @param  Closure(list<array{par: int, strokeIndex: int, distance: int|null}>): list<array{par: int, strokeIndex: int, distance: int|null}>  $changeHoles
 */
function baseWithReferenceRound(Closure $changeHoles): LegacySqliteFixture
{
    $card = referenceCardData(null, null);

    return LegacySqliteFixture::atLevel()
        ->player(1, 'Gab', 1791400000000)
        ->player(2, 'Manu', 1791500000000)
        ->round(1, 'BETHPAGE BLACK', 'Red', '2026-10-17', 1792251843742, $changeHoles($card['holes']), [
            [1, $card['lines'][0]['strokes']],
            [2, $card['lines'][1]['strokes']],
        ])
        ->stats(1, 1, 72, 72, 10, null, 10, true)
        ->stats(1, 2, 88, 88, 170, null, 170, true);
}

// Spec « Distance 0 ».
// Mutation that turns it red: the distance 0 copied as is (the CHECK of round_holes refuses it).
test('a distance out of 1 to 999 is imported empty and counted', function () {
    $base = baseWithReferenceRound(function (array $holes): array {
        $holes[3]['distance'] = 0;

        return $holes;
    });

    [$exitCode, $lines] = importSqlite($base->path);

    expect($exitCode)->toBe(0)
        ->and(DB::table('round_holes')->orderBy('hole')->pluck('distance')->all())
        ->toBe([420, 344, 128, null, 398, 377, 480, 155, 392, 353, 405, 392, 470, 149, 401, 423, 173, 337])
        ->and($lines)->toContain('Distances hors de 1 à 999 vidées : 1');
});

// Spec « Ligne refusée par une contrainte ».
// Mutation that turns it red: the rows inserted outside the transaction (the players of the
// base stay).
test('a row refused by a constraint cancels the whole import and names its SQLite row', function () {
    $card = referenceCardData(null, null);
    $holes = $card['holes'];
    $holes[6]['par'] = 6;
    $base = baseWithReferenceRound(fn (array $holes): array => $holes)
        ->round(2, 'BETHPAGE BLACK', 'Red', '2026-10-24', 1792856643742, $holes, [[1, $card['lines'][0]['strokes']]]);

    [$exitCode, $lines] = importSqlite($base->path);

    expect($exitCode)->toBe(1)
        ->and($lines[0])->toStartWith('Ligne refusée : round_holes, round 2, trou 7 : ')
        ->and($lines[0])->toContain('round_holes_par_check')
        ->and(nonEmptyTargetTables())->toBe([])
        ->and(Storage::disk('photos')->allFiles())->toBe([]);
});

// Mutation that turns it red: a reference without its row given a null UUID instead of failing
// (PostgreSQL then names scores without the SQLite keys).
test('a score of a player missing from the base fails and names the reference', function () {
    $base = baseWithReferenceRound(fn (array $holes): array => $holes)
        ->execute('pragma foreign_keys = off')
        ->execute('insert into scores (round_id, player_id, hole, strokes) values (1, 9, 1, 4)');

    [$exitCode, $lines] = importSqlite($base->path);

    expect($exitCode)->toBe(1)
        ->and($lines)->toBe(['Référence absente : scores, round 1, joueur 9'])
        ->and(nonEmptyTargetTables())->toBe([]);
});

// SQLite keeps a text in an integer column. Mutation that turns it red: LegacyRow::int() casts
// the value instead of failing (the round would be dated 1970-01-01).
test('a value of the wrong type in the base fails and names its column', function () {
    $base = baseWithReferenceRound(fn (array $holes): array => $holes)
        ->execute("update rounds set created_at = 'hier' where id = 1");

    [$exitCode, $lines] = importSqlite($base->path);

    expect($exitCode)->toBe(1)
        ->and($lines)->toBe(['Valeur illisible : rounds.created_at'])
        ->and(nonEmptyTargetTables())->toBe([]);
});

// Spec « Import en une seule transaction »: the photos already copied go with the rows.
// uploads/a.webp is copied first, then uploads/b.jpg cannot be decoded.
// Mutation that turns it red: a photo written by the import not deleted on failure.
test('a failure after a photo is copied deletes the photo', function () {
    $base = baseWithReferenceRound(fn (array $holes): array => $holes)
        ->import(1, 'uploads/a.webp', 1792251800000, ['round_id' => 1])
        ->import(2, 'uploads/b.jpg', 1792251900000)
        ->webpPhoto('a.webp');
    file_put_contents($base->uploads.'/b.jpg', 'not an image');

    [$exitCode, $lines] = importSqlite($base->path, ['--uploads' => $base->uploads]);

    expect($exitCode)->toBe(1)
        ->and($lines)->toBe(['Photo illisible : uploads/b.jpg'])
        ->and(nonEmptyTargetTables())->toBe([])
        ->and(Storage::disk('photos')->allFiles())->toBe([]);
});
