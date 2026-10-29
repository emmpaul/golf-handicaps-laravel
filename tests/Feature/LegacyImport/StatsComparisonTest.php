<?php

/*
 * Spec legacy-import « Statistiques recalculées et comparées » and « Rien dans le journal de
 * l'application »; design D11. The statistics written in SQLite are those of D15, recomputed by
 * hand with the WHS table: no rating, so the par 71 and 113; Gab 72 → 10, Manu 88 → 170, Gab
 * +17 → 170, then the provisional index of two rounds (170 + 10) / 2 = 90.
 */

use Illuminate\Support\Facades\DB;
use Tests\Support\LegacySqlite\LegacySqliteFixture;

/**
 * The reference card IMG_3064 of Gab (1) and Manu (2), Sean (3) on no round, without any row of
 * player_round_stats: each test writes its own.
 */
function cardWithoutStats(): LegacySqliteFixture
{
    $card = referenceCardData(null, null);

    return LegacySqliteFixture::atLevel()
        ->player(1, 'Gab', 1791400000000)
        ->player(2, 'Manu', 1791500000000)
        ->player(3, 'Sean', 1791600000000)
        ->round(1, 'BETHPAGE BLACK', 'Red', '2026-10-17', 1792251843742, $card['holes'], [
            [1, $card['lines'][0]['strokes']],
            [2, $card['lines'][1]['strokes']],
        ]);
}

/**
 * The rows recomputed in PostgreSQL, by player name: gross, adjusted, differential, index before
 * and after, counts in current.
 *
 * @return array<string, list<array{int, int, int, int|null, int, bool}>>
 */
function recomputedStats(): array
{
    $rows = DB::table('player_round_stats')
        ->join('players', 'players.id', '=', 'player_round_stats.player_id')
        ->join('rounds', 'rounds.id', '=', 'player_round_stats.round_id')
        ->orderBy('players.name')->orderBy('rounds.played_on')
        ->get(['players.name', 'gross', 'adjusted', 'differential_t', 'index_before_t', 'index_after_t', 'counts_in_current']);

    $stats = [];
    foreach ($rows as $row) {
        $stats[$row->name][] = [$row->gross, $row->adjusted, $row->differential_t, $row->index_before_t, $row->index_after_t, $row->counts_in_current];
    }

    return $stats;
}

/**
 * Imports $base: its exit code, the rows recomputed by player name, the line of the report on
 * the statistics.
 *
 * @return array{int, array<string, list<array{int, int, int, int|null, int, bool}>>, list<string>}
 */
function importedStats(LegacySqliteFixture $base): array
{
    [$exitCode, $lines] = importSqlite($base->path);

    return [$exitCode, recomputedStats(), array_values(preg_grep('/^Statistiques identiques/', $lines) ?: [])];
}

// Spec « Statistiques identiques ».
// Mutation that turns it red: the line « Statistiques identiques » left out of the report.
test('the statistics of the reference card are recomputed equal to SQLite', function () {
    $base = cardWithoutStats()
        ->stats(1, 1, 72, 72, 10, null, 10, true)
        ->stats(1, 2, 88, 88, 170, null, 170, true);

    expect(importedStats($base))->toBe([0, [
        'Gab' => [[72, 72, 10, null, 10, true]],
        'Manu' => [[88, 88, 170, null, 170, true]],
    ], ['Statistiques identiques : 2 / 2']]);
});

// D15: Gab +17 on 2026-10-10, before the reference card.
// Mutation that turns it red: the SQLite rows read on round 1 only (where round_id = 1): the
// row of Gab on 2026-10-10 is then absent from SQLite.
test('a history of two rounds is recomputed equal to SQLite', function () {
    $base = cardWithoutStats()
        ->round(2, 'BETHPAGE BLACK', 'Red', '2026-10-10', 1791650000000, referenceCardData(null, null)['holes'], [[1, strokesOverPar(17)]])
        ->stats(2, 1, 88, 88, 170, null, 170, true)
        ->stats(1, 1, 72, 72, 10, 170, 90, true)
        ->stats(1, 2, 88, 88, 170, null, 170, true);

    expect(importedStats($base))->toBe([0, [
        'Gab' => [[88, 88, 170, null, 170, true], [72, 72, 10, 170, 90, true]],
        'Manu' => [[88, 88, 170, null, 170, true]],
    ], ['Statistiques identiques : 3 / 3']]);
});

// Spec « Écart ».
// Mutation that turns it red: player_round_stats copied from SQLite instead of recomputed (the
// differential 171 is then equal on both sides).
test('a differential that differs fails the import and is listed', function () {
    $base = cardWithoutStats()
        ->stats(1, 1, 72, 72, 10, null, 10, true)
        ->stats(1, 2, 88, 88, 171, null, 170, true);

    [$exitCode, $lines] = importSqlite($base->path);

    expect($exitCode)->toBe(1)
        ->and($lines)->toBe([
            'Statistiques différentes de la base SQLite :',
            'BETHPAGE BLACK / Red, 2026-10-17, Manu : differential_t SQLite 171, recalculé 170',
            'Import annulé : rien n\'a été écrit.',
        ])
        ->and(nonEmptyTargetTables())->toBe([]);
});

// Mutation that turns it red: counts_in_current not compared.
test('a row that counts in the index in one base only is listed', function () {
    $base = cardWithoutStats()
        ->stats(1, 1, 72, 72, 10, null, 10, false)
        ->stats(1, 2, 88, 88, 170, null, 170, true);

    [$exitCode, $lines] = importSqlite($base->path);

    expect($exitCode)->toBe(1)
        ->and($lines)->toContain('BETHPAGE BLACK / Red, 2026-10-17, Gab : counts_in_current SQLite non, recalculé oui');
});

// Mutation that turns it red: the comparison stopped at the first difference.
test('every difference is listed, one line each', function () {
    $base = cardWithoutStats()
        ->stats(1, 1, 72, 72, 10, null, 10, false)
        ->stats(1, 2, 88, 88, 171, null, 170, true);

    [$exitCode, $lines] = importSqlite($base->path);

    expect($exitCode)->toBe(1)
        ->and($lines)->toBe([
            'Statistiques différentes de la base SQLite :',
            'BETHPAGE BLACK / Red, 2026-10-17, Gab : counts_in_current SQLite non, recalculé oui',
            'BETHPAGE BLACK / Red, 2026-10-17, Manu : differential_t SQLite 171, recalculé 170',
            'Import annulé : rien n\'a été écrit.',
        ]);
});

// A SQLite row of a player who has no line on the round.
// Mutation that turns it red: only the recomputed rows looked up in SQLite (the extra SQLite row
// is never seen).
test('a SQLite row that the computation does not give is listed', function () {
    $base = cardWithoutStats()
        ->stats(1, 1, 72, 72, 10, null, 10, true)
        ->stats(1, 2, 88, 88, 170, null, 170, true)
        ->stats(1, 3, 80, 80, 90, null, 90, true);

    [$exitCode, $lines] = importSqlite($base->path);

    expect($exitCode)->toBe(1)
        ->and($lines)->toBe([
            'Statistiques différentes de la base SQLite :',
            'BETHPAGE BLACK / Red, 2026-10-17, Sean : absente du recalcul',
            'Import annulé : rien n\'a été écrit.',
        ]);
});

// Mutation that turns it red: only the SQLite rows looked up in the computation (the row of Manu
// missing in SQLite is never seen).
test('a recomputed row that SQLite does not have is listed', function () {
    $base = cardWithoutStats()->stats(1, 1, 72, 72, 10, null, 10, true);

    [$exitCode, $lines] = importSqlite($base->path);

    expect($exitCode)->toBe(1)
        ->and($lines)->toBe([
            'Statistiques différentes de la base SQLite :',
            'BETHPAGE BLACK / Red, 2026-10-17, Manu : absente de SQLite',
            'Import annulé : rien n\'a été écrit.',
        ]);
});

// Spec « Échec sur un écart ».
// Mutation that turns it red: Log::error of each difference.
test('a failure on a difference writes no player nor course in the application log', function () {
    $log = storage_path('logs/legacy-import-'.bin2hex(random_bytes(6)).'.log');
    config([
        'logging.default' => 'legacy-import-test',
        'logging.channels.legacy-import-test' => ['driver' => 'single', 'path' => $log, 'level' => 'debug'],
    ]);
    $base = cardWithoutStats()
        ->stats(1, 1, 72, 72, 10, null, 10, true)
        ->stats(1, 2, 88, 88, 171, null, 170, true);

    [$exitCode] = importSqlite($base->path);

    $written = is_file($log) ? (string) file_get_contents($log) : '';
    @unlink($log);
    expect($exitCode)->toBe(1)
        ->and($written)->not->toContain('Manu')
        ->and($written)->not->toContain('BETHPAGE');
});
