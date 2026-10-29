<?php

/*
 * Spec legacy-import « Essai à blanc »; design D3, D9, D12.
 */

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\LegacySqlite\LegacySqliteFixture;

beforeEach(function () {
    Storage::fake('photos');
    Queue::fake();
});

// Spec « Essai réussi ».
// Mutations that turn it red: the photos written in a dry run (copy() without its dry-run
// branch); the lookups of rating queued in a dry run (dispatched when the dry run is cancelled).
test('a dry run does the whole import, prints the report and writes nothing', function () {
    $base = LegacySqliteFixture::countsSet();

    [$exitCode, $lines] = importSqlite($base->path, ['--uploads' => $base->uploads, '--dry-run' => true]);

    expect($exitCode)->toBe(0)
        ->and($lines)->toContain('Photos lues : 2')
        ->and($lines)->toContain('Recherches de rating à remettre en file : 1')
        ->and($lines[array_key_last($lines)])->toBe('Essai à blanc : import annulé, rien n\'a été écrit.')
        ->and(nonEmptyTargetTables())->toBe([])
        ->and(Storage::disk('photos')->allFiles())->toBe([]);
    Queue::assertNothingPushed();
});

// Spec « Essai qui échoue ».
// Mutation that turns it red: the photos not checked in a dry run.
test('a dry run that misses a photo lists it and fails', function () {
    $base = LegacySqliteFixture::countsSet();
    unlink($base->uploads.'/9b7c.webp');

    [$exitCode, $lines] = importSqlite($base->path, ['--uploads' => $base->uploads, '--dry-run' => true]);

    expect($exitCode)->toBe(1)
        ->and($lines)->toBe(['Photos manquantes :', 'uploads/9b7c.webp']);
});

// Spec « Essai à blanc »: the comparison of the statistics runs in a dry run too.
// Mutation that turns it red: the comparison skipped in a dry run.
test('a dry run that finds a difference of statistics lists it and fails', function () {
    $base = LegacySqliteFixture::referenceRound()
        ->execute('update player_round_stats set differential_t = 171 where player_id = 2');

    [$exitCode, $lines] = importSqlite($base->path, ['--dry-run' => true]);

    expect($exitCode)->toBe(1)
        ->and($lines)->toBe([
            'Statistiques différentes de la base SQLite :',
            'BETHPAGE BLACK / Red, 2026-10-17, Manu : differential_t SQLite 171, recalculé 170',
            'Import annulé : rien n\'a été écrit.',
        ])
        ->and(nonEmptyTargetTables())->toBe([]);
});

// Design D12: --dry-run --force asks too, empties inside the transaction, and keeps everything.
// Mutation that turns it red: the dry run committed (the base replaces the initial players).
test('a confirmed dry run keeps the rows it emptied', function () {
    config(['golf.initial_players' => 'Gab,Manu,Sean']);
    Artisan::call('golf:initial-players');
    $base = LegacySqliteFixture::countsSet();

    $this->artisan('golf:import-sqlite', ['db' => $base->path, '--uploads' => $base->uploads, '--force' => true, '--dry-run' => true])
        ->expectsConfirmation('Vider players (3), player_aliases (3) avant l\'import ?', 'yes')
        ->expectsOutputToContain('Essai à blanc : import annulé, rien n\'a été écrit.')
        ->assertExitCode(0);

    expect(nonEmptyTargetTables())->toBe(['players' => 3, 'player_aliases' => 3])
        ->and(DB::table('player_aliases')->orderBy('alias')->pluck('alias')->all())->toBe(['GAB', 'MANU', 'SEAN']);
});
