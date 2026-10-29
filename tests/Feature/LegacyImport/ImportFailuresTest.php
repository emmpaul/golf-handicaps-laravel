<?php

/*
 * Spec legacy-import « Import en une seule transaction » (the message names the step) and « Rien
 * dans le journal de l'application »; design D3, D13. The failures that are not a row refused by
 * a constraint: a step of the transaction, then a step after the commit.
 */

use App\Models\Round;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\LegacySqlite\LegacySqliteFixture;

// A PostgreSQL error inside the recompute, raised by a trigger of the test on the rows it writes.
// Mutation that turns it red: the QueryException of RecomputePlayerStats let through (the
// command then ends on an uncaught exception instead of a message).
test('a database error in the recompute names the step and writes nothing', function () {
    DB::unprepared(<<<'SQL'
        create function refuse_stats() returns trigger language plpgsql as $$
        begin raise exception 'statistiques refusées par le test'; end $$;
        create trigger refuse_stats before insert on player_round_stats for each row execute function refuse_stats();
        SQL);
    $base = LegacySqliteFixture::referenceRound();

    [$exitCode, $lines] = importSqlite($base->path);

    expect($exitCode)->toBe(1)
        ->and($lines[0])->toStartWith('Échec du recalcul des statistiques : ERROR:  statistiques refusées par le test')
        ->and(nonEmptyTargetTables())->toBe([]);
});

/**
 * The reference round with its photo uploads/3f2a.webp, and the folder imports/ of the photos
 * disk read only: no photo can be written there.
 */
function baseWithUnwritablePhotos(): LegacySqliteFixture
{
    Storage::disk('photos')->makeDirectory('imports');
    chmod(Storage::disk('photos')->path('imports'), 0555);

    return LegacySqliteFixture::referenceRound(photoPath: 'uploads/3f2a.webp')->webpPhoto('3f2a.webp');
}

// The folders made read only by a test are given back, so that the fake disk can be emptied.
afterEach(function () {
    foreach (['imports', 'rounds'] as $folder) {
        $path = Storage::disk('photos')->path($folder);
        if (is_dir($path)) {
            chmod($path, 0755);
        }
    }
});

// A disk that reports a failed write by its return value, as the photos disk does by default.
// Mutation that turns it red: the result of put() ignored (the import ends in « Import terminé. »
// with a round whose photo is missing).
test('a photo that cannot be written names the step and writes nothing', function () {
    Storage::fake('photos');
    $base = baseWithUnwritablePhotos();

    [$exitCode, $lines] = importSqlite($base->path, ['--uploads' => $base->uploads]);

    expect($exitCode)->toBe(1)
        ->and($lines)->toBe(['Échec de la copie des photos : uploads/3f2a.webp → imports/3f2a.webp'])
        ->and(nonEmptyTargetTables())->toBe([]);
});

// A disk that throws on a failed write: the exception of the filesystem, or of Imagick at the
// conversion, other than UnreadablePhoto, goes through the same catch.
// Mutation that turns it red: only UnreadablePhoto caught around the copy of a photo (the
// UnableToWriteFile of Flysystem then ends the command uncaught).
test('an exception while a photo is copied names the step and writes nothing', function () {
    Storage::fake('photos', ['throw' => true]);
    $base = baseWithUnwritablePhotos();

    [$exitCode, $lines] = importSqlite($base->path, ['--uploads' => $base->uploads]);

    expect($exitCode)->toBe(1)
        ->and($lines[0])->toStartWith('Échec de la copie des photos : uploads/3f2a.webp → imports/3f2a.webp : Unable to write file')
        ->and(nonEmptyTargetTables())->toBe([]);
});

/**
 * Routes the application log to a file of the test, read back by loggedText().
 */
function logToTestFile(): string
{
    $log = storage_path('logs/legacy-import-'.bin2hex(random_bytes(6)).'.log');
    config([
        'logging.default' => 'legacy-import-test',
        'logging.channels.legacy-import-test' => ['driver' => 'single', 'path' => $log, 'level' => 'debug'],
    ]);

    return $log;
}

function loggedText(string $log): string
{
    $written = is_file($log) ? (string) file_get_contents($log) : '';
    @unlink($log);

    return $written;
}

// Design D3, step 4: the import is committed; a queue out of reach is told, not thrown.
// Mutation that turns it red: the dispatch outside a try (the exception of the queue ends the
// command after the commit, the report never printed).
test('a lookup that cannot be queued after the commit is told at the end of the report', function () {
    $log = logToTestFile();
    config(['queue.default' => 'hors-service', 'queue.connections.hors-service' => ['driver' => 'hors-service']]);
    $base = LegacySqliteFixture::referenceRound()->courseRating(1, 'BETHPAGE BLACK', 'Red', 1792624190600);

    [$exitCode, $lines] = importSqlite($base->path);

    expect($exitCode)->toBe(0)
        ->and(array_slice($lines, -1))->toBe(['Import terminé ; échec de la mise en file de 1 recherche de rating : la relancer depuis la page Parcours (« Relancer la recherche »).'])
        ->and(DB::table('course_ratings')->pluck('status')->all())->toBe(['pending'])
        ->and(loggedText($log))->toBe('');
});

// Design D3, step 4: the photos of the emptied rows go after the commit; one that stays is told.
// Mutation that turns it red: the result of delete() ignored (« Import terminé. » with the old
// photo still on the disk).
test('a photo of the emptied rows that cannot be deleted is told at the end of the report', function () {
    $log = logToTestFile();
    Storage::fake('photos');
    Round::factory()->create(['photo_path' => 'rounds/ancienne.webp']);
    Storage::disk('photos')->put('rounds/ancienne.webp', 'old');
    chmod(Storage::disk('photos')->path('rounds'), 0555);
    $base = LegacySqliteFixture::referenceRound();

    $this->artisan('golf:import-sqlite', ['db' => $base->path, '--force' => true])
        ->expectsConfirmation('Vider rounds (1) avant l\'import ?', 'yes')
        ->expectsOutput('Import terminé ; échec de l\'effacement de 1 photo des lignes vidées : rounds/ancienne.webp')
        ->doesntExpectOutput('Import terminé.')
        ->assertExitCode(0);

    expect(Storage::disk('photos')->allFiles())->toBe(['rounds/ancienne.webp'])
        ->and(loggedText($log))->toBe('');
});
