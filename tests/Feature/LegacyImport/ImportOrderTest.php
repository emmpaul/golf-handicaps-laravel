<?php

use App\Domain\Players\Queries\PlayerList;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\LegacySqlite\LegacySqliteFixture;

// The UUIDs the import draws, in descending order: drawn in that order, they would reverse the
// rows. The import sorts them before giving them to the SQLite ids (design D5).
beforeEach(fn () => Str::createUuidsUsingSequence([
    '019a3000-0000-7000-8000-000000000003',
    '019a3000-0000-7000-8000-000000000002',
    '019a3000-0000-7000-8000-000000000001',
]));

afterEach(fn () => Str::createUuidsNormally());

/**
 * @return array<string, int> the colour slot of each player, by name
 */
function colorSlotsByName(): array
{
    return collect(app(PlayerList::class)->get())->pluck('colorSlot', 'name')->all();
}

// Spec « Joueurs créés dans la même milliseconde ».
// Mutation that turns it red: the UUIDs given to the ids in the order they are drawn, without
// sorting them first.
test('two players created in the same millisecond keep the order of their SQLite ids', function () {
    $base = LegacySqliteFixture::atLevel()
        ->player(1, 'Gab', 1791400000000)
        ->player(2, 'Manu', 1791500000500)
        ->player(3, 'Sean', 1791500000500);

    [$exitCode] = importSqlite($base->path);

    expect($exitCode)->toBe(0)
        ->and(DB::table('players')->orderBy('created_at')->orderBy('id')->pluck('name')->all())->toBe(['Gab', 'Manu', 'Sean'])
        ->and(colorSlotsByName())->toBe(['Gab' => 0, 'Manu' => 1, 'Sean' => 2]);
});

// Same mutation: the order follows the ids, not the names.
test('the player of the lower SQLite id comes first whatever his name', function () {
    $base = LegacySqliteFixture::atLevel()
        ->player(1, 'Gab', 1791400000000)
        ->player(2, 'Sean', 1791500000500)
        ->player(3, 'Manu', 1791500000500);

    importSqlite($base->path);

    expect(DB::table('players')->orderBy('created_at')->orderBy('id')->pluck('name')->all())->toBe(['Gab', 'Sean', 'Manu'])
        ->and(colorSlotsByName())->toBe(['Gab' => 0, 'Manu' => 2, 'Sean' => 1]);
});

// Mutation that turns it red: the UUIDs of the rounds given unsorted; the second round then
// comes first in the history of Gab.
test('two rounds of the same day created in the same second keep their SQLite order in the history', function () {
    Str::createUuidsUsingSequence([
        '019a3000-0000-7000-8000-000000000009',
        '019a3000-0000-7000-8000-000000000002',
        '019a3000-0000-7000-8000-000000000001',
    ]);
    $holes = referenceCardData(null, null)['holes'];
    $base = LegacySqliteFixture::atLevel()
        ->player(1, 'Gab', 1791400000000)
        ->round(1, 'BETHPAGE BLACK', 'Red', '2026-10-17', 1792251843100, $holes, [[1, strokesOverPar(17)]])
        ->round(2, 'BETHPAGE BLACK', 'Red', '2026-10-17', 1792251843900, $holes, [[1, strokesOverPar(1)]]);

    importSqlite($base->path);

    $history = DB::table('player_round_stats')->orderByDesc('gross')
        ->get(['gross', 'index_before_t', 'index_after_t'])
        ->map(fn (object $stat): array => [$stat->gross, $stat->index_before_t, $stat->index_after_t])
        ->all();

    expect($history)->toBe([[88, null, 170], [72, 170, 90]]);
});
