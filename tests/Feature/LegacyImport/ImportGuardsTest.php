<?php

/*
 * Spec legacy-import « Refus sur une base cible non vide » and « Vidage confirmé avec --force »;
 * design D3, D12.
 */

use App\Models\Import;
use App\Models\Round;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\PendingCommand;
use Tests\Support\LegacySqlite\LegacySqliteFixture;

/** The 1×1 WebP of the round photo already on the disk before the import. */
const OLD_PHOTO = 'UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA==';

beforeEach(function () {
    Storage::fake('photos');
    config(['golf.initial_players' => 'Gab,Manu,Sean']);
    Artisan::call('golf:initial-players');
});

/**
 * A base of Next with Alex and Bea, who play the reference card on 2026-10-17 (round 1, photo
 * uploads/nouvelle.webp), with the statistics Next computed for their first round.
 */
function guardsBase(): LegacySqliteFixture
{
    return LegacySqliteFixture::referenceRound('Alex', 'Bea', 'uploads/nouvelle.webp')->webpPhoto('nouvelle.webp');
}

/**
 * A round of the target base with its photo rounds/ancienne.webp on the disk.
 */
function roundWithOldPhoto(): void
{
    Round::factory()->create(['photo_path' => 'rounds/ancienne.webp']);
    Storage::disk('photos')->put('rounds/ancienne.webp', (string) base64_decode(OLD_PHOTO, true));
}

/**
 * golf:import-sqlite --force on $base, answering $answer to the question that empties $tables.
 */
function importWithForce(LegacySqliteFixture $base, string $tables, string $answer = 'yes'): PendingCommand
{
    return test()->artisan('golf:import-sqlite', ['db' => $base->path, '--uploads' => $base->uploads, '--force' => true])
        ->expectsConfirmation("Vider {$tables} avant l'import ?", $answer);
}

/**
 * The initial players, their aliases and the round of roundWithOldPhoto(), as before the import.
 */
function expectTargetAsBefore(): void
{
    expect(playerNames())->toBe(['Gab', 'Manu', 'Sean'])
        ->and(DB::table('player_aliases')->count())->toBe(3)
        ->and(DB::table('rounds')->pluck('photo_path')->all())->toBe(['rounds/ancienne.webp'])
        ->and(Storage::disk('photos')->allFiles())->toBe(['rounds/ancienne.webp']);
}

/**
 * @return list<string>
 */
function playerNames(): array
{
    return DB::table('players')->orderBy('name')->pluck('name')->all();
}

// Spec « Joueurs initiaux déjà créés ».
// Mutation that turns it red: the check of the target tables removed (Alex and Bea imported
// next to the initial players).
test('an import on a base that has rows is refused and names each table with its count', function () {
    $base = guardsBase();
    [$exitCode, $lines] = importSqlite($base->path, ['--uploads' => $base->uploads]);

    expect($exitCode)->toBe(1)
        ->and($lines)->toBe(['Tables non vides : players (3), player_aliases (3). Relancer avec --force pour les vider.'])
        ->and(playerNames())->toBe(['Gab', 'Manu', 'Sean']);
});

// Spec « Confirmation acceptée ».
// Mutation that turns it red: the tables not emptied when force is confirmed (refused as not
// empty).
test('with --force and a yes, the target tables are emptied and replaced by the base', function () {
    $base = guardsBase();

    importWithForce($base, 'players (3), player_aliases (3)')
        ->assertExitCode(0);

    expect(playerNames())->toBe(['Alex', 'Bea'])
        ->and(DB::table('player_aliases')->count())->toBe(0);
});

// Spec « Confirmation refusée ».
// Mutation that turns it red: the answer of the confirmation ignored (the import runs).
test('with --force and a no, the import is abandoned and nothing is written', function () {
    $base = guardsBase();

    importWithForce($base, 'players (3), player_aliases (3)', 'no')
        ->expectsOutputToContain('Import abandonné.')
        ->assertExitCode(1);

    expect(playerNames())->toBe(['Gab', 'Manu', 'Sean']);
});

// Spec « Sans terminal ».
// Mutation that turns it red: the confirmation asked with yes as its default.
test('with --force and no terminal, the confirmation is refused', function () {
    $base = guardsBase();
    [$exitCode, $lines] = importSqlite($base->path, ['--uploads' => $base->uploads, '--force' => true]);

    expect($exitCode)->toBe(1)
        ->and($lines)->toBe(['Import abandonné.'])
        ->and(playerNames())->toBe(['Gab', 'Manu', 'Sean']);
});

// Mutation that turns it red: the confirmation asked whatever the counts (the question was not
// expected by the test).
test('with --force on an empty target base, no question is asked', function () {
    DB::table('players')->delete();
    $base = guardsBase();

    $this->artisan('golf:import-sqlite', ['db' => $base->path, '--uploads' => $base->uploads, '--force' => true])
        ->assertExitCode(0);

    expect(playerNames())->toBe(['Alex', 'Bea']);
});

// Spec « Import qui échoue après le vidage ».
// Mutation that turns it red: the tables emptied by the command as soon as the answer is yes,
// before the checks of the base.
test('a confirmed import that misses a photo leaves the rows and the photos of before', function () {
    roundWithOldPhoto();
    $base = guardsBase();
    unlink($base->uploads.'/nouvelle.webp');

    importWithForce($base, 'players (3), player_aliases (3), rounds (1)')
        ->assertExitCode(1);

    expectTargetAsBefore();
});

// Mutations that turn it red: TRUNCATE outside the transaction (the initial players are gone),
// the old photo deleted before the commit.
test('a confirmed import refused by a constraint after the emptying leaves everything as before', function () {
    roundWithOldPhoto();
    $card = referenceCardData(null, null);
    $holes = $card['holes'];
    $holes[6]['par'] = 6;
    $base = guardsBase()->round(2, 'BETHPAGE BLACK', 'Red', '2026-10-24', 1792856643742, $holes, [[1, $card['lines'][0]['strokes']]]);

    importWithForce($base, 'players (3), player_aliases (3), rounds (1)')
        ->assertExitCode(1);

    expectTargetAsBefore();
});

// Spec « Vidage confirmé avec --force »: the photos of the emptied rows go once committed.
// Mutation that turns it red: the photos of the emptied rows left on the disk.
test('a confirmed import deletes the photos of the emptied rows after the commit', function () {
    roundWithOldPhoto();
    $base = guardsBase();

    importWithForce($base, 'players (3), player_aliases (3), rounds (1)')
        ->assertExitCode(0);

    expect(Storage::disk('photos')->allFiles())->toBe(['imports/nouvelle.webp']);
});

// A base imported again with --force: its photo has the path of the photo already there.
// Mutation that turns it red: a photo written over an existing file deleted on failure instead
// of given back its bytes of before.
test('a failed import gives back its bytes to a photo it wrote over', function () {
    $old = (string) base64_decode(OLD_PHOTO, true);
    Import::factory()->failed('Panneau introuvable sur la photo')->create(['photo_path' => 'imports/nouvelle.webp']);
    Storage::disk('photos')->put('imports/nouvelle.webp', $old);
    $base = guardsBase()->import(1, 'uploads/zz-illisible.jpg', 1792251800000);
    file_put_contents($base->uploads.'/zz-illisible.jpg', 'not an image');

    importWithForce($base, 'players (3), player_aliases (3), imports (1)')
        ->expectsOutputToContain('Photo illisible : uploads/zz-illisible.jpg')
        ->assertExitCode(1);

    expect(Storage::disk('photos')->get('imports/nouvelle.webp'))->toBe($old)
        ->and(DB::table('imports')->pluck('photo_path')->all())->toBe(['imports/nouvelle.webp']);
});
