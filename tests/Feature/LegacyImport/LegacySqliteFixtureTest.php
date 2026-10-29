<?php

use Tests\Support\LegacySqlite\LegacySqliteFixture;

// Smoke test of the fixture of the import tests (design D15 of sqlite-import-command).
// Mutation that turns it red: migrate() applies every file whatever the level asked.
test('the fixture migrates the base of Next up to the level asked', function () {
    expect(LegacySqliteFixture::atLevel('0004')->tables())->toBe([
        'ai_usage', 'course_ratings', 'imports', 'jobs', 'player_aliases', 'player_round_stats',
        'players', 'round_holes', 'round_players', 'rounds', 'scores',
    ])
        ->and(LegacySqliteFixture::atLevel('0003')->tables())->toBe([
            'ai_usage', 'imports', 'jobs', 'player_aliases', 'player_round_stats',
            'players', 'round_holes', 'round_players', 'rounds', 'scores',
        ]);
});
