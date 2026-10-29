<?php

/*
 * Spec handicap-index, « Lignes de règle du calcul de l'index » (« Différentiels retenus »,
 * « Ajustement », « Index provisoire ») and design D2 of index-computation-page: the rule the page
 * « Calcul de l'index » reads, provisional index included.
 *
 * Mutations that turn these tests red:
 * - 6 rounds retaining 1 differential instead of 2: [1, -10] for 6;
 * - the provisional case dropped (under 3 rounds, the line of 3): [1, -20] for 1 and 2;
 * - 20 rounds retaining 7: [7, 0] for 20.
 */

use App\Domain\Handicaps\Whs;

test('the WHS rule gives the differentials retained and the adjustment for n rounds', function (int $rounds, array $rule) {
    expect(Whs::retainedAndAdjustment($rounds))->toBe($rule);
})->with([
    '1 round, provisional' => [1, [1, 0]],
    '2 rounds, provisional' => [2, [2, 0]],
    '3 rounds' => [3, [1, -20]],
    '4 rounds' => [4, [1, -10]],
    '5 rounds' => [5, [1, 0]],
    '6 rounds' => [6, [2, -10]],
    '8 rounds' => [8, [2, 0]],
    '9 rounds' => [9, [3, 0]],
    '20 rounds' => [20, [8, 0]],
]);

test('half-up rounding goes to the nearest integer, 0,5 up, negative values included', function (float $value, int $rounded) {
    expect(Whs::roundHalfUp($value))->toBe($rounded);
})->with([
    [132.5, 133],
    [132.49, 132],
    [-10.5, -10],
]);
