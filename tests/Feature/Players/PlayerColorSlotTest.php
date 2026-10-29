<?php

/*
 * Spec visual-theme « Couleur de joueur » (« Joueurs de développement », « Septième joueur »,
 * « Renommage »); design D6: the colour of a player is its rank of creation over the whole
 * players table, modulo 6, computed in SQL and exposed as colorSlot (0 to 5).
 *
 * Mutations that turn these tests red:
 * - the rank ordered by name instead of the creation (order by name, id): Sean (created third)
 *   would take slot 2 only by chance, Manu renamed « Emmanuel » moves to slot 0;
 * - modulo 5 instead of 6: the seventh player gets 1 instead of 0;
 * - the ties of created_at not broken by id: the two players created at the same instant swap.
 */

use App\Models\Player;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Creates the players in this order, one second apart.
 *
 * @return list<Player>
 */
function playersCreatedInOrder(string ...$names): array
{
    return array_map(fn (string $name, int $offset): Player => Player::factory()->create([
        'name' => $name,
        'created_at' => CarbonImmutable::parse('2026-10-01 12:00:00', 'UTC')->addSeconds($offset),
    ]), $names, array_keys($names));
}

/**
 * @return array<string, int> the colorSlot of each player of /players, by name
 */
function colorSlotsOnPlayersPage(): array
{
    return collect(test()->get('/players')->assertOk()->inertiaProps('players'))
        ->mapWithKeys(fn (array $player): array => [$player['name'] => $player['colorSlot']])
        ->all();
}

beforeEach(function () {
    $this->withPasscodeSession();
});

test('Gab, Manu then Sean take the slots 0, 1 and 2 on /players', function () {
    playersCreatedInOrder('Gab', 'Manu', 'Sean');

    expect(colorSlotsOnPlayersPage())->toBe(['Gab' => 0, 'Manu' => 1, 'Sean' => 2]);
});

test('the leaderboard and the card of a round carry the slot of each player', function () {
    [$gab, $manu] = playersCreatedInOrder('Gab', 'Manu', 'Sean');
    $round = createRoundCard(referenceCardData($gab->id, $manu->id));

    $props = $this->get("/rounds/{$round->id}")->assertOk()->inertiaProps('round');

    expect(array_map(fn (array $entry): array => [$entry['name'], $entry['colorSlot']], $props['leaderboard']))
        ->toBe([['Gab', 0], ['Manu', 1]])
        ->and(array_map(fn (array $line): array => [$line['name'], $line['colorSlot']], $props['card']['lines']))
        ->toBe([['Gab', 0], ['Manu', 1]]);
});

test('a seventh player takes the slot of the first again', function () {
    playersCreatedInOrder('Gab', 'Manu', 'Sean', 'Alex', 'Bea', 'Chloe', 'Dan');

    expect(colorSlotsOnPlayersPage())->toBe([
        'Alex' => 3, 'Bea' => 4, 'Chloe' => 5, 'Dan' => 0, 'Gab' => 0, 'Manu' => 1, 'Sean' => 2,
    ]);
});

test('Manu renamed « Emmanuel » keeps the slot 1', function () {
    [, $manu] = playersCreatedInOrder('Gab', 'Manu', 'Sean');

    $this->patch("/players/{$manu->id}", ['name' => 'Emmanuel'])->assertRedirect();

    expect(colorSlotsOnPlayersPage())->toBe(['Emmanuel' => 1, 'Gab' => 0, 'Sean' => 2]);
});

test('two players created at the same instant are ranked by id', function () {
    $at = CarbonImmutable::parse('2026-10-01 12:00:00', 'UTC');
    $first = Player::factory()->create(['name' => 'Zoe', 'created_at' => $at]);
    $second = Player::factory()->create(['name' => 'Ada', 'created_at' => $at]);
    // Ids are UUID v7, increasing with time: Zoe, created first, has the smaller id.
    expect(strcmp($first->id, $second->id))->toBeLessThan(0);
    DB::table('players')->update(['created_at' => $at]);

    expect(colorSlotsOnPlayersPage())->toBe(['Ada' => 1, 'Zoe' => 0]);
});
