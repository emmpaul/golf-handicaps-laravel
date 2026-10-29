<?php

/*
 * The simulator prints its dates month/day/year.
 *
 * Mutations that turn these tests red:
 * - day and month read the other way: « 03/04/2026 » gives 2026-04-03 and « 10/17/2026 » null;
 * - checkdate() not called: « 02/30/2026 » gives a date.
 */

use App\Domain\Imports\SimulatorDate;

test('a simulator date reads month, day, year', function (?string $text, ?string $date) {
    expect(SimulatorDate::parse($text))->toBe($date);
})->with([
    ['DATE: 10/17/2026', '2026-10-17'],
    ['03/04/2026', '2026-03-04'],
    ['13/45/2026', null],
    ['02/30/2026', null],
    ['', null],
    [null, null],
]);
