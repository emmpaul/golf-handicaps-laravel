<?php

/*
 * Design D10 of sqlite-import-command: the correction Next saved (savedCorrectionSchema of
 * src/scorecard/form-schema.ts) in the shape of the port, { version: 1, card }.
 */

use App\Domain\LegacyImport\IdMap;
use App\Domain\LegacyImport\LegacyImportCorrection;
use Illuminate\Support\Str;
use Tests\TestCase;

// ImportCorrection validates through the Validator facade: the application must be booted.
pest()->extend(TestCase::class);

const NEXT_GAB_UUID = '019a3000-0000-7000-8000-000000000001';

const NEXT_PARS = [4, 4, 3, 5, 4, 4, 5, 3, 4, 4, 4, 4, 5, 3, 4, 4, 3, 4];

const NEXT_STROKE_INDEXES = [8, 16, 18, 2, 4, 10, 6, 14, 12, 9, 11, 7, 3, 17, 1, 5, 13, 15];

const NEXT_DISTANCES = [420, 344, 128, 444, 398, 377, 480, 155, 392, 353, 405, 392, 470, 149, 401, 423, 173, 337];

const NEXT_GAB_STROKES = [5, 5, 6, 5, 4, 4, 4, 3, 5, 4, 4, 4, 4, 3, 4, 5, 3, 3];

beforeEach(function () {
    Str::createUuidsUsingSequence([NEXT_GAB_UUID]);
    $this->ids = new IdMap;
    $this->ids->assign('players', [1]);
});

afterEach(fn () => Str::createUuidsNormally());

/**
 * A correction of Next: one line named GAB on the SQLite player $playerId, 6 at hole 3.
 *
 * @return array<string, mixed>
 */
function correctionOfNext(?int $playerId, bool $totalsRead): array
{
    return [
        'version' => 1,
        'card' => [
            'courseName' => 'BETHPAGE BLACK',
            'tees' => 'Red',
            'playedOn' => '2026-10-17',
            'par' => ['holes' => NEXT_PARS, 'out' => 36, 'in' => 35, 'total' => 71],
            'strokeIndex' => NEXT_STROKE_INDEXES,
            'distance' => ['holes' => NEXT_DISTANCES, 'out' => 3139, 'in' => 3103, 'total' => 6241],
        ],
        'readCardTotals' => $totalsRead,
        'lines' => [[
            'name' => 'GAB',
            'playerId' => $playerId,
            'scores' => ['holes' => NEXT_GAB_STROKES, 'out' => 41, 'in' => 34, 'total' => 75],
            'readTotals' => $totalsRead,
        ]],
        'aiCells' => ['player0:3'],
    ];
}

/**
 * @return list<array{par: int, strokeIndex: int, distance: int}>
 */
function holesOfThePort(): array
{
    return array_map(
        fn (int $par, int $strokeIndex, int $distance): array => ['par' => $par, 'strokeIndex' => $strokeIndex, 'distance' => $distance],
        NEXT_PARS, NEXT_STROKE_INDEXES, NEXT_DISTANCES,
    );
}

// Mutation that turns it red: the SQLite player id kept (the uuid rule refuses it: null).
test('a correction of Next is translated with its player remapped and the totals read', function () {
    expect(LegacyImportCorrection::translate(correctionOfNext(1, true), $this->ids))->toBe([
        'version' => 1,
        'card' => [
            'courseName' => 'BETHPAGE BLACK',
            'tees' => 'Red',
            'playedOn' => '2026-10-17',
            'holes' => holesOfThePort(),
            'lines' => [[
                'playerId' => NEXT_GAB_UUID,
                'strokes' => NEXT_GAB_STROKES,
                'readName' => 'GAB',
                'totals' => ['out' => 41, 'in' => 34, 'total' => 75],
            ]],
            'parTotals' => ['out' => 36, 'in' => 35, 'total' => 71],
        ],
    ]);
});

// Mutation that turns it red: the totals set whether read or not.
test('totals not read are left out', function () {
    expect(LegacyImportCorrection::translate(correctionOfNext(1, false), $this->ids))->toBe([
        'version' => 1,
        'card' => [
            'courseName' => 'BETHPAGE BLACK',
            'tees' => 'Red',
            'playedOn' => '2026-10-17',
            'holes' => holesOfThePort(),
            'lines' => [[
                'playerId' => NEXT_GAB_UUID,
                'strokes' => NEXT_GAB_STROKES,
                'readName' => 'GAB',
            ]],
        ],
    ]);
});

// Mutation that turns it red: an unknown SQLite player failing the import (IdMap::uuid()
// instead of a lookup that may find nothing).
test('a line on a player missing from the base has no player', function () {
    $correction = LegacyImportCorrection::translate(correctionOfNext(9, true), $this->ids);

    expect($correction['card']['lines'] ?? null)->toBe([[
        'playerId' => null,
        'strokes' => NEXT_GAB_STROKES,
        'readName' => 'GAB',
        'totals' => ['out' => 41, 'in' => 34, 'total' => 75],
    ]]);
});

// Mutation that turns it red: a shape not checked before it is read (an error instead of null).
test('a correction out of the shape of Next is dropped', function (array $correction) {
    expect(LegacyImportCorrection::translate($correction, $this->ids))->toBeNull();
})->with([
    'card not an object' => [['version' => 1, 'card' => 'x', 'readCardTotals' => true, 'lines' => [], 'aiCells' => []]],
    'par without holes' => [['version' => 1, 'card' => ['par' => []], 'readCardTotals' => true, 'lines' => [], 'aiCells' => []]],
    'no line' => [[...correctionOfNext(1, true), 'lines' => []]],
    'line without scores' => [[...correctionOfNext(1, true), 'lines' => [['name' => 'GAB', 'playerId' => 1, 'readTotals' => true]]]],
]);
