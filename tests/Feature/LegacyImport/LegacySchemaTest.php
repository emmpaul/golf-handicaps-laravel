<?php

use App\Domain\LegacyImport\LegacyDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\LegacySqlite\LegacySqliteFixture;

// Mutation that turns it red: LegacySchema::check() refuses a table that has more columns than
// expected (columns compared for equality instead of inclusion).
test('a base at the level of 0004 is accepted, an extra column included', function () {
    $base = LegacySqliteFixture::atLevel('0004')
        ->execute('alter table players add column nickname text')
        ->player(1, 'Gab', 1792251843742);

    [$exitCode] = importSqlite($base->path);

    expect($exitCode)->toBe(0)
        ->and(DB::table('players')->pluck('name')->all())->toBe(['Gab']);
});

// Mutation that turns it red: course_ratings removed from LegacySchema::TABLES.
test('a base of before the ratings is refused before any write', function () {
    $base = LegacySqliteFixture::atLevel('0003')->player(1, 'Gab', 1792251843742);

    [$exitCode, $lines] = importSqlite($base->path);

    expect($exitCode)->toBe(1)
        ->and($lines)->toBe([
            'Base SQLite au mauvais niveau : migration Drizzle 0004 attendue.',
            'Table manquante : course_ratings',
        ])
        ->and(nonEmptyTargetTables())->toBe([]);
});

// Mutation that turns it red: correction removed from the columns of imports in
// LegacySchema::TABLES.
test('a base without a column of 0004 is refused and the column named', function () {
    $base = LegacySqliteFixture::atLevel('0004')
        ->execute('alter table imports drop column correction')
        ->player(1, 'Gab', 1792251843742);

    [$exitCode, $lines] = importSqlite($base->path);

    expect($exitCode)->toBe(1)
        ->and($lines)->toBe([
            'Base SQLite au mauvais niveau : migration Drizzle 0004 attendue.',
            'Colonne manquante : imports.correction',
        ])
        ->and(nonEmptyTargetTables())->toBe([]);
});

// Design D4: jobs is only counted in the report, but read; a base without it would fail at the
// count, after every insertion.
// Mutation that turns it red: jobs removed from LegacySchema::TABLES (the import then ends on
// the « no such table » of SQLite).
test('a base without the table jobs is refused before any write', function () {
    $base = LegacySqliteFixture::atLevel('0004')->execute('drop table jobs')->player(1, 'Gab', 1792251843742);

    [$exitCode, $lines] = importSqlite($base->path);

    expect($exitCode)->toBe(1)
        ->and($lines)->toBe([
            'Base SQLite au mauvais niveau : migration Drizzle 0004 attendue.',
            'Table manquante : jobs',
        ])
        ->and(nonEmptyTargetTables())->toBe([]);
});

// Mutation that turns it red: the check throws at the first missing table or column.
test('every missing table and column is listed, not only the first', function () {
    $base = LegacySqliteFixture::atLevel('0003')->execute('alter table imports drop column correction');

    [$exitCode, $lines] = importSqlite($base->path);

    expect($exitCode)->toBe(1)
        ->and($lines)->toBe([
            'Base SQLite au mauvais niveau : migration Drizzle 0004 attendue.',
            'Colonne manquante : imports.correction',
            'Table manquante : course_ratings',
        ]);
});

// Mutation that turns it red: the file is not checked before the connection is declared (the
// driver then throws its own SQLiteDatabaseDoesNotExistException).
test('a missing base is named and nothing is written', function () {
    [$exitCode, $lines] = importSqlite('/tmp/absente.db');

    expect($exitCode)->toBe(1)
        ->and($lines)->toBe(['Base SQLite introuvable : /tmp/absente.db'])
        ->and(nonEmptyTargetTables())->toBe([]);
});

// Mutation that turns it red: the read-only flag removed from the options of the legacy
// connection.
test('the legacy connection refuses any write', function () {
    $base = LegacySqliteFixture::atLevel('0004');

    $legacy = LegacyDatabase::open($base->path)->connection();

    expect(fn () => $legacy->table('players')->insert(['id' => 1, 'name' => 'Gab', 'created_at' => 1792251843742]))
        ->toThrow(QueryException::class, 'attempt to write a readonly database');
});

// Mutation that turns it red: the check of bytes 18-19 of the header removed from
// LegacyDatabase::open() (the base is then read, its -wal and -shm files created, and the
// import succeeds).
test('a base in WAL mode is refused, read without creating a -wal or -shm file', function () {
    $base = LegacySqliteFixture::atLevel('0004')->player(1, 'Gab', 1792251843742)->inWalMode();
    $fingerprint = hash_file('sha256', $base->path);

    [$exitCode, $lines] = importSqlite($base->path);

    expect($exitCode)->toBe(1)
        ->and($lines)->toBe(['Base en WAL : copier par VACUUM INTO (docs/deployment.md, Bascule depuis Next).'])
        ->and([file_exists($base->path.'-wal'), file_exists($base->path.'-shm')])->toBe([false, false])
        ->and(hash_file('sha256', $base->path))->toBe($fingerprint)
        ->and(nonEmptyTargetTables())->toBe([]);
});
