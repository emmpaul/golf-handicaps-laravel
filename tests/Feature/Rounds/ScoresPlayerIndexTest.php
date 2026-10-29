<?php

/*
 * L15, DO41 of visual-redesign: the STATS block (PlayerScoringStats) and the history read the
 * scores of one player; the unique (round_id, player_id, hole) starts with round_id and cannot
 * serve them. The migration adds an index on scores(player_id) and its down() drops it.
 *
 * Mutations that turn these tests red:
 * - the migration removed (or indexing round_id): no index on player_id alone;
 * - down() left empty: the index is still there after it.
 */

use Illuminate\Support\Facades\Schema;

/** The column lists of the indexes of scores. */
function scoresIndexColumns(): array
{
    return array_map(fn (array $index): array => $index['columns'], Schema::getIndexes('scores'));
}

test('scores carry an index on player_id alone', function () {
    expect(scoresIndexColumns())->toContain(['player_id']);
});

test('the migration of the index rolls back', function () {
    $migration = require database_path('migrations/2026_10_29_000012_add_player_index_to_scores_table.php');

    $migration->down();
    expect(scoresIndexColumns())->not->toContain(['player_id']);

    $migration->up();
    expect(scoresIndexColumns())->toContain(['player_id']);
});
