<?php

/*
 * Design D3 (correction after review, L7): a SIGINT or a SIGTERM between the copy of the photos
 * and the commit would leave photos on the disk that no row references, or lose the bytes of a
 * photo written over. The command blocks both signals for the whole import: the signal waits for
 * the commit, or for the rollback and the undo of the photos, then ends the process.
 */

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Tests\Support\LegacySqlite\LegacySqliteFixture;

/**
 * @return list<int> the signals blocked in this process now
 */
function blockedSignals(): array
{
    // pcntl_sigprocmask() needs a signal: SIGWINCH, that nothing blocks, is unblocked to read
    // the mask before the call.
    $blocked = [];
    pcntl_sigprocmask(SIG_UNBLOCK, [SIGWINCH], $blocked);

    return array_values($blocked);
}

// The mask is read inside the transaction, at the first row of statistics the recompute writes:
// after the copy of the photos, before the commit.
// Mutations that turn it red: the signals never blocked (SIGINT and SIGTERM missing from the mask
// read in the transaction), the mask not given back after the import (both still blocked).
test('SIGINT and SIGTERM wait for the end of the import', function () {
    $duringImport = null;
    DB::listen(function (QueryExecuted $query) use (&$duringImport): void {
        if ($duringImport === null && str_starts_with($query->sql, 'insert into "player_round_stats"')) {
            $duringImport = blockedSignals();
        }
    });
    $base = LegacySqliteFixture::referenceRound(photoPath: 'uploads/3f2a.webp')->webpPhoto('3f2a.webp');

    [$exitCode] = importSqlite($base->path, ['--uploads' => $base->uploads]);

    expect($exitCode)->toBe(0)
        ->and(array_values(array_intersect([SIGINT, SIGTERM], $duringImport ?? [])))->toBe([SIGINT, SIGTERM])
        ->and(array_values(array_intersect([SIGINT, SIGTERM], blockedSignals())))->toBe([]);
});
