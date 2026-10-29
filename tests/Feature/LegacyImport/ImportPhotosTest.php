<?php

/*
 * Spec legacy-import « Photos copiées » and « Photo manquante ou chemin refusé »; design D9.
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\LegacySqlite\LegacySqliteFixture;

beforeEach(fn () => Storage::fake('photos'));

// Spec « Photo partagée ».
// Mutation that turns it red: one copy per row (the round and the import each count a copy).
test('a photo shared by a round and its import is copied once, byte for byte, and served to both', function () {
    $base = LegacySqliteFixture::referenceRound(photoPath: 'uploads/3f2a.webp')
        ->import(1, 'uploads/3f2a.webp', 1792251800000, ['round_id' => 1])
        ->webpPhoto('3f2a.webp');

    [$exitCode, $lines] = importSqlite($base->path, ['--uploads' => $base->uploads]);

    $round = DB::table('rounds')->first();
    $import = DB::table('imports')->first();
    expect($exitCode)->toBe(0)
        ->and($lines)->toContain('Photos copiées : 1')
        ->and(Storage::disk('photos')->allFiles())->toBe(['imports/3f2a.webp'])
        ->and(Storage::disk('photos')->get('imports/3f2a.webp'))->toBe(file_get_contents(database_path('data/img3064.webp')))
        ->and([$round->photo_path, $import->photo_path])->toBe(['imports/3f2a.webp', 'imports/3f2a.webp']);

    $this->withPasscodeSession();
    $this->get("/rounds/{$round->id}/photo")->assertOk()->assertHeader('Content-Type', 'image/webp');
    $this->get("/imports/{$import->id}/photo")->assertOk()->assertHeader('Content-Type', 'image/webp');
});

// Spec « Photo en JPEG ».
// Mutation that turns it red: the JPEG copied without conversion.
test('a photo that is not a WebP is converted to WebP', function () {
    $base = LegacySqliteFixture::referenceRound(photoPath: 'uploads/ancienne.jpg')->jpegPhoto('ancienne.jpg');

    [$exitCode] = importSqlite($base->path, ['--uploads' => $base->uploads]);

    $photo = (string) Storage::disk('photos')->get('imports/ancienne.webp');
    expect($exitCode)->toBe(0)
        ->and(DB::table('rounds')->value('photo_path'))->toBe('imports/ancienne.webp')
        ->and(substr($photo, 0, 4))->toBe('RIFF')
        ->and(substr($photo, 8, 4))->toBe('WEBP');
});

// Spec « Photos manquantes ».
// Mutation that turns it red: the check stopped at the first missing photo.
test('every missing photo is listed before any write', function () {
    $card = referenceCardData(null, null);
    $base = LegacySqliteFixture::referenceRound(photoPath: 'uploads/a.webp')
        ->round(2, 'BETHPAGE BLACK', 'Red', '2026-10-24', 1792856643742, $card['holes'], [[1, $card['lines'][0]['strokes']]], 'uploads/b.webp');

    [$exitCode, $lines] = importSqlite($base->path, ['--uploads' => $base->uploads]);

    expect($exitCode)->toBe(1)
        ->and($lines)->toBe(['Photos manquantes :', 'uploads/a.webp', 'uploads/b.webp'])
        ->and(nonEmptyTargetTables())->toBe([])
        ->and(Storage::disk('photos')->allFiles())->toBe([]);
});

// Spec « Chemin hors du dossier ». uploads/../app.db is the base itself, next to the folder.
// Mutation that turns it red: the name of the file not checked (the base is read as a photo).
test('a path out of the uploads folder is refused and never read', function () {
    $base = LegacySqliteFixture::referenceRound()->import(1, 'uploads/../app.db', 1792251800000);

    [$exitCode, $lines] = importSqlite($base->path, ['--uploads' => $base->uploads]);

    expect($exitCode)->toBe(1)
        ->and($lines)->toBe(['Chemin de photo refusé : uploads/../app.db'])
        ->and(nonEmptyTargetTables())->toBe([]);
});

// Design D9, decision of the orchestrator: two sources that give the same file are refused,
// before any write.
// Mutation that turns it red: the collision ignored (the second copy overwrites the first).
test('two photos that give the same file on the disk are refused with both paths', function () {
    $card = referenceCardData(null, null);
    $base = LegacySqliteFixture::referenceRound(photoPath: 'uploads/a.webp')
        ->round(2, 'BETHPAGE BLACK', 'Red', '2026-10-24', 1792856643742, $card['holes'], [[1, $card['lines'][0]['strokes']]], 'uploads/a.jpg')
        ->stats(2, 1, 72, 72, 10, 10, 10, true)
        ->webpPhoto('a.webp')
        ->jpegPhoto('a.jpg');

    [$exitCode, $lines] = importSqlite($base->path, ['--uploads' => $base->uploads]);

    expect($exitCode)->toBe(1)
        ->and($lines)->toBe(['Photos de même destination :', 'uploads/a.jpg et uploads/a.webp → imports/a.webp'])
        ->and(nonEmptyTargetTables())->toBe([])
        ->and(Storage::disk('photos')->allFiles())->toBe([]);
});

// Mutation that turns it red: only the photos of the rounds counted (the import adds one).
test('photos referenced without --uploads are refused with their count', function () {
    $base = LegacySqliteFixture::referenceRound(photoPath: 'uploads/3f2a.webp')->import(1, 'uploads/9b7c.webp', 1792251800000);

    [$exitCode, $lines] = importSqlite($base->path);

    expect($exitCode)->toBe(1)
        ->and($lines)->toBe(['Option --uploads requise : 2 photos référencées.']);
});

// Mutation that turns it red: the files of the folder not counted (unreferencedFiles() gives 0).
test('a file of the folder that no row references is ignored and counted', function () {
    $base = LegacySqliteFixture::referenceRound(photoPath: 'uploads/3f2a.webp')->webpPhoto('3f2a.webp')->webpPhoto('orpheline.webp');

    [$exitCode, $lines] = importSqlite($base->path, ['--uploads' => $base->uploads]);

    expect($exitCode)->toBe(0)
        ->and($lines)->toContain('Fichiers non référencés ignorés : 1')
        ->and(Storage::disk('photos')->allFiles())->toBe(['imports/3f2a.webp']);
});
