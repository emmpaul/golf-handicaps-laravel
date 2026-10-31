<?php

/*
 * Spec legacy-import « Imports repris » and « Correction reprise ou abandonnée »; design D10.
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\LegacySqlite\LegacySqliteFixture;

beforeEach(fn () => Storage::fake('photos'));

/**
 * The reference round of Gab and Manu; every import added by the test shares uploads/p.webp.
 */
function baseForImports(): LegacySqliteFixture
{
    return LegacySqliteFixture::referenceRound()->webpPhoto('p.webp');
}

/**
 * The correction Next saved for the reference card: line 1 on the SQLite player $playerId, 6 at
 * hole 3, the totals of the card and of the line read.
 *
 * @return array<string, mixed>
 */
function nextCorrection(?int $playerId): array
{
    $card = referenceCardData(null, null);

    return [
        'version' => 1,
        'card' => [
            'courseName' => 'BETHPAGE BLACK',
            'tees' => 'Red',
            'playedOn' => '2026-10-17',
            'par' => ['holes' => array_column($card['holes'], 'par'), 'out' => 36, 'in' => 35, 'total' => 71],
            'strokeIndex' => array_column($card['holes'], 'strokeIndex'),
            'distance' => ['holes' => array_column($card['holes'], 'distance'), 'out' => 3139, 'in' => 3103, 'total' => 6241],
        ],
        'readCardTotals' => true,
        'lines' => [[
            'name' => 'GAB',
            'playerId' => $playerId,
            'scores' => ['holes' => [5, 5, 6, 5, 4, 4, 4, 3, 5, 4, 4, 4, 4, 3, 4, 5, 3, 3], 'out' => 41, 'in' => 34, 'total' => 75],
            'readTotals' => true,
        ]],
        'aiCells' => ['player0:3'],
    ];
}

// Spec « Import déjà enregistré ».
// Mutation that turns it red: the draft copied as is, without ScorecardDraft (the tees keep the
// distance read after their name).
test('a ready import saved as a round keeps its draft, as ScorecardDraft writes it, and its round', function () {
    $draft = json_decode(LegacySqliteFixture::referenceDraftJson(), true);
    $draft['tees'] = 'Red 6241yd';
    $base = baseForImports()->import(1, 'uploads/p.webp', 1792251800000, ['round_id' => 1, 'draft' => json_encode($draft)]);

    [$exitCode] = importSqlite($base->path, ['--uploads' => $base->uploads]);

    $import = DB::table('imports')->sole();
    expect($exitCode)->toBe(0)
        ->and($import->status)->toBe('ready')
        ->and($import->failure)->toBeNull()
        // jsonb orders the keys its own way: compared as maps.
        ->and(json_decode($import->draft, true))->toEqual([
            'courseName' => 'BETHPAGE BLACK',
            'tees' => 'Red',
            'playedOn' => '2026-10-17',
            'par' => ['holes' => [4, 4, 3, 5, 4, 4, 5, 3, 4, 4, 4, 4, 5, 3, 4, 4, 3, 4], 'out' => 36, 'in' => 35, 'total' => 71],
            'strokeIndex' => [8, 16, 18, 2, 4, 10, 6, 14, 12, 9, 11, 7, 3, 17, 1, 5, 13, 15],
            'distance' => ['holes' => [420, 344, 128, 444, 398, 377, 480, 155, 392, 353, 405, 392, 470, 149, 401, 423, 173, 337], 'out' => 3139, 'in' => 3103, 'total' => 6241],
            'players' => [
                ['name' => 'GAB', 'scores' => ['holes' => [5, 5, 3, 5, 4, 4, 4, 3, 5, 4, 4, 4, 4, 3, 4, 5, 3, 3], 'out' => 38, 'in' => 34, 'total' => 72]],
                ['name' => 'MANU', 'scores' => ['holes' => [7, 5, 3, 7, 6, 5, 5, 3, 5, 3, 6, 5, 7, 4, 5, 4, 3, 5], 'out' => 46, 'in' => 42, 'total' => 88]],
            ],
        ]);

    $this->withPasscodeSession()->get("/imports/{$import->id}")->assertRedirect("/rounds/{$import->round_id}");
});

// Spec « Import interrompu ».
// Mutation that turns it red: queued and reading kept as they are.
test('an import queued or being read becomes a failure interrupted by the migration', function () {
    $base = baseForImports()
        ->import(1, 'uploads/p.webp', 1792251800000, ['status' => 'reading', 'draft' => null])
        ->import(2, 'uploads/p.webp', 1792251900000, ['status' => 'queued', 'draft' => null]);

    [$exitCode] = importSqlite($base->path, ['--uploads' => $base->uploads]);

    $statuses = DB::table('imports')->orderBy('created_at')->get(['status', 'failure'])->map(fn (object $row): array => (array) $row)->all();
    expect($exitCode)->toBe(0)
        ->and($statuses)->toBe([
            ['status' => 'failed', 'failure' => 'Import interrompu par la migration'],
            ['status' => 'failed', 'failure' => 'Import interrompu par la migration'],
        ]);

    config(['ai.providers.anthropic.key' => 'test-key']);
    $actions = $this->withPasscodeSession()->get('/import')->assertOk()->inertiaProps('imports');
    expect(array_column($actions, 'actions'))->toBe([
        ['correctEmpty', 'retry', 'delete'],
        ['correctEmpty', 'retry', 'delete'],
    ]);
});

// Mutations that turn it red: the note of Next dropped on a failure, the default reason missing
// (failure left null).
test('a failed import keeps its note of Next as reason, or the panel not found without one', function () {
    $base = baseForImports()
        ->import(1, 'uploads/p.webp', 1792251800000, ['status' => 'failed', 'draft' => null, 'ai_note' => 'Complétion IA indisponible'])
        ->import(2, 'uploads/p.webp', 1792251900000, ['status' => 'failed', 'draft' => null]);

    importSqlite($base->path, ['--uploads' => $base->uploads]);

    expect(DB::table('imports')->orderBy('created_at')->pluck('failure')->all())
        ->toBe(['Complétion IA indisponible', 'Panneau introuvable sur la photo']);
});

// Mutation that turns it red: the note of Next copied on a ready import.
test('the note of a ready import is dropped', function () {
    $base = baseForImports()->import(1, 'uploads/p.webp', 1792251800000, ['ai_note' => 'Budget IA du mois atteint']);

    importSqlite($base->path, ['--uploads' => $base->uploads]);

    expect(DB::table('imports')->sole()->failure)->toBeNull();
});

// Mutation that turns it red: a draft refused by ScorecardDraft written as is.
test('a draft out of shape fails the import and names the import', function (?string $draft) {
    $base = baseForImports()
        ->import(1, 'uploads/p.webp', 1792251800000)
        ->import(2, 'uploads/p.webp', 1792251850000)
        ->import(3, 'uploads/p.webp', 1792251900000, ['draft' => $draft]);

    [$exitCode, $lines] = importSqlite($base->path, ['--uploads' => $base->uploads]);

    expect($exitCode)->toBe(1)
        ->and($lines)->toBe(['Brouillon illisible : import 3'])
        ->and(nonEmptyTargetTables())->toBe([]);
})->with([
    'row of 17 holes' => ['{"courseName":null,"tees":null,"playedOn":null,"par":{"holes":[4,4,3],"out":null,"in":null,"total":null},"strokeIndex":[],"distance":{"holes":[],"out":null,"in":null,"total":null},"players":[]}'],
    'not JSON' => ['{"courseName":'],
    'ready without draft' => [null],
]);

// Corners, their source and the cells completed by the AI are dropped.
// Mutation that turns it red: ai_cells copied (the table has no such column: the row is refused).
test('the imported row has only the columns of the port', function () {
    $base = baseForImports()->import(1, 'uploads/p.webp', 1792251800000, [
        'corners' => '[[0,0],[1,0],[1,1],[0,1]]',
        'corners_source' => 'auto',
        'ai_cells' => '["player0:3"]',
    ]);

    importSqlite($base->path, ['--uploads' => $base->uploads]);

    $row = (array) DB::table('imports')->sole();

    // upload_key (offline-pwa, design D8) belongs to the photos sent by the device: none here.
    expect(array_keys($row))
        ->toBe(['id', 'status', 'photo_path', 'draft', 'correction', 'failure', 'round_id', 'created_at', 'updated_at', 'upload_key'])
        ->and($row['upload_key'])->toBeNull();
});

// Spec « Vérification reprise ».
// Mutation that turns it red: the correction of an import still to check dropped.
test('the correction of an import still to check opens with its player and its strokes', function () {
    $base = baseForImports()->import(1, 'uploads/p.webp', 1792251800000, ['correction' => json_encode(nextCorrection(1))]);

    importSqlite($base->path, ['--uploads' => $base->uploads]);

    $import = DB::table('imports')->sole();
    $gab = DB::table('players')->where('name', 'Gab')->value('id');
    $line = $this->withPasscodeSession()->get("/imports/{$import->id}")->assertOk()->inertiaProps('editor.card.lines.0');

    expect($line['playerId'])->toBe($gab)
        ->and($line['strokes'][2])->toBe(6);
});

// Spec « Correction d'un import enregistré ».
// Mutation that turns it red: the correction kept on an import saved as a round.
test('the correction of an import saved as a round is dropped and counted', function () {
    $card = referenceCardData(null, null);
    $base = baseForImports()
        ->round(2, 'BETHPAGE BLACK', 'Red', '2026-10-24', 1792856643742, $card['holes'], [[1, $card['lines'][0]['strokes']]])
        ->stats(2, 1, 72, 72, 10, 10, 10, true)
        ->import(1, 'uploads/p.webp', 1792251800000, ['round_id' => 1, 'correction' => json_encode(nextCorrection(1))])
        ->import(2, 'uploads/p.webp', 1792856600000, ['round_id' => 2, 'correction' => json_encode(nextCorrection(2))]);

    [$exitCode, $lines] = importSqlite($base->path, ['--uploads' => $base->uploads]);

    expect($exitCode)->toBe(0)
        ->and(DB::table('imports')->pluck('correction')->all())->toBe([null, null])
        ->and($lines)->toContain('Corrections abandonnées : 2');
});
