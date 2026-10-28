<?php

/*
 * Mutations that turn these tests red:
 * - strtoupper instead of mb_strtoupper in alias(): « émile » becomes « éMILE »;
 * - no trim in alias() or name(): the surrounding spaces stay.
 */

use App\Domain\Players\PlayerName;

test('an alias is trimmed and upper cased', function (string $raw, string $alias) {
    expect(PlayerName::alias($raw))->toBe($alias);
})->with([
    'surrounding spaces' => ['  seanp ', 'SEANP'],
    'accented letter' => ['émile', 'ÉMILE'],
]);

test('a name is trimmed and keeps its case', function () {
    expect(PlayerName::name('  Alex '))->toBe('Alex');
});
