<?php

/*
 * tests/fixtures/scorecards/gspro-img3064.draft.json is the reference draft copied from the Next
 * application; ImportFactory reads the same file, so there is a single copy.
 *
 * Mutations that turn these tests red:
 * - the length of a row not checked: the line of 17 scores gives a draft;
 * - a total accepted when it is not an integer: the « 38 » string gives a draft;
 * - the number of lines not bounded: the 5 lines give a draft;
 * - a row read from the wrong key (strokeIndex read from par): the pars or toArray() differ.
 */

use App\Domain\Imports\ScorecardDraft;

/**
 * @return array<string, mixed>
 */
function referenceDraftArray(): array
{
    return json_decode((string) file_get_contents(__DIR__.'/../../fixtures/scorecards/gspro-img3064.draft.json'), true, flags: JSON_THROW_ON_ERROR);
}

test('the reference draft reads back its pars, its names and itself', function () {
    $draft = ScorecardDraft::fromArray(referenceDraftArray());

    expect($draft)->not->toBeNull()
        ->and($draft->par['holes'])->toBe([4, 4, 3, 5, 4, 4, 5, 3, 4, 4, 4, 4, 5, 3, 4, 4, 3, 4])
        ->and(array_column($draft->players, 'name'))->toBe(['GAB', 'MANU'])
        ->and($draft->toArray())->toBe(referenceDraftArray());
});

test('a card out of the expected shape gives no draft', function (callable $alter) {
    $card = referenceDraftArray();
    $alter($card);

    expect(ScorecardDraft::fromArray($card))->toBeNull();
})->with([
    'a line of 17 scores' => [function (array &$card): void {
        array_pop($card['players'][0]['scores']['holes']);
    }],
    'a total « 38 » as a string' => [function (array &$card): void {
        $card['players'][0]['scores']['out'] = '38';
    }],
    '5 lines' => [function (array &$card): void {
        $card['players'] = array_fill(0, 5, $card['players'][0]);
    }],
]);

test('empty cells and an empty card stay a draft', function () {
    $card = referenceDraftArray();
    $card['players'][0]['scores']['holes'][8] = null;
    $card['players'][0]['scores']['total'] = null;
    $card['courseName'] = null;
    $card['players'][1]['name'] = null;

    $draft = ScorecardDraft::fromArray($card);

    expect($draft?->toArray())->toBe($card);
});
