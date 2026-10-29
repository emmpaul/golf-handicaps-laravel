<?php

/*
 * tests/fixtures/round-checks.json is shared with resources/js/lib/round-checks.test.ts: a gap
 * between the PHP and TypeScript checks turns one of the two red.
 *
 * Mutations that turn these tests red:
 * - the score bound at 14: « Score 16 hors de 1 à 14 » instead of « … 1 à 15 », and the score
 *   of 15 of the « score 15 » case is refused;
 * - the duplicate index not detected: the « index 1 en double » case finds no problem;
 * - the distance not checked: the « distance 1000 » case finds no problem;
 * - « problèmes » without the plural rule: problemCountLabel(1) says « 1 problèmes ».
 * - the back sum compared over holes 10 to 17: « Somme retour 31 ≠ IN 35 » instead of « 34 », and
 *   the cards whose totals are right get a back sum problem;
 * - an empty read total ignored: the « total lu vide » case finds no problem.
 */

use App\Domain\Rounds\Problem;
use App\Domain\Rounds\RoundCard;
use App\Domain\Rounds\RoundCardChecks;

/**
 * @return array<string, array{array<string, mixed>, list<array{message: string, cells: list<string>}>}>
 */
function roundCheckCases(): array
{
    $fixture = json_decode((string) file_get_contents(__DIR__.'/../../fixtures/round-checks.json'), true, flags: JSON_THROW_ON_ERROR);

    $cases = [];
    foreach ($fixture['cases'] as $case) {
        $cases[$case['name']] = [$case['card'], $case['problems']];
    }

    return $cases;
}

test('the shared table has its cases', function () {
    expect(array_keys(roundCheckCases()))->toBe([
        'carte de référence',
        'distances toutes vides',
        'index 1 en double',
        'par 6',
        'score 16',
        'score 15',
        'distance 1000',
        'case vide',
        'ligne sans joueur',
        'joueur en double',
        '0 ligne',
        '5 lignes',
        'parcours vide',
        'tees absents',
        'date vide',
        'somme aller fausse',
        'OUT + IN faux',
        'somme retour fausse',
        'total lu vide',
        'par OUT 37',
        'carte sans totaux lus',
    ]);
});

test('a card gets the problems of the shared table', function (array $card, array $problems) {
    $found = array_map(
        fn (Problem $problem): array => ['message' => $problem->message, 'cells' => $problem->cells],
        RoundCardChecks::problems(RoundCard::fromArray($card)),
    );

    expect($found)->toBe($problems);
})->with(roundCheckCases());

test('the problem count reads in French', function (int $count, string $label) {
    expect(RoundCardChecks::problemCountLabel($count))->toBe($label);
})->with([
    [1, '1 problème à corriger'],
    [3, '3 problèmes à corriger'],
]);
