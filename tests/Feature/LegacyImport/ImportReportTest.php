<?php

/*
 * Spec legacy-import « Rapport d'import »; design D13.
 */

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\LegacySqlite\LegacySqliteFixture;

beforeEach(function () {
    Storage::fake('photos');
    Queue::fake();
});

// Spec « Import réel », on the base of « Comptes identiques »: 3 players, 7 aliases, 2 rounds,
// 36 holes, 4 lines, 72 scores, 4 rows of statistics, 2 ratings (1 pending), 2 imports with 2
// photos, 3 rows of ai_usage, 5 jobs. No name of player in the report.
// Mutations that turn it red: player_round_stats left out of the table; the jobs of Next not
// counted.
test('the report of a real import gives every count, line for line', function () {
    $base = LegacySqliteFixture::countsSet();

    [$exitCode, $lines] = importSqlite($base->path, ['--uploads' => $base->uploads]);

    expect($exitCode)->toBe(0)
        ->and($lines)->toBe([
            'Table              SQLite PostgreSQL',
            'players                 3          3',
            'player_aliases          7          7',
            'rounds                  2          2',
            'round_holes            36         36',
            'round_players           4          4',
            'scores                 72         72',
            'player_round_stats      4          4',
            'course_ratings          2          2',
            'imports                 2          2',
            'ai_usage                3          3',
            'Photos copiées : 2',
            'Fichiers non référencés ignorés : 0',
            'Statistiques identiques : 4 / 4',
            'Distances hors de 1 à 999 vidées : 0',
            'Corrections abandonnées : 0',
            'Recherches de rating remises en file : 1',
            'Jobs Next ignorés : 5',
            'Import terminé.',
        ]);
});
