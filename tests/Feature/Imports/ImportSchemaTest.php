<?php

/*
 * The constraints of imports and ai_usage hold in PostgreSQL, below the actions (design D1).
 * Rows are written by DB::table: the ImportStatus cast would refuse an unknown status before
 * the base sees it.
 *
 * Mutations that turn these tests red:
 * - the CHECK of imports.status removed: « done » is inserted;
 * - the CHECK status <> 'ready' OR draft IS NOT NULL removed: a ready import without draft is inserted;
 * - imports.round_id without unique: a second import of the same round is inserted;
 * - the round_id foreign key in nullOnDelete instead of cascade: the import stays, round_id null;
 * - the CHECK cost_micro_usd >= 0 removed: a negative cost is inserted.
 */

use App\Models\AiUsage;
use App\Models\Import;
use App\Models\Round;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $columns
 */
function insertImport(array $columns): void
{
    DB::table('imports')->insert([
        'id' => (string) Str::uuid(),
        'status' => 'queued',
        'photo_path' => 'imports/'.Str::uuid().'.webp',
        'created_at' => now(),
        'updated_at' => now(),
        ...$columns,
    ]);
}

test('an unknown status is refused', function () {
    expect(fn () => insertImport(['status' => 'done']))
        ->toThrow(QueryException::class, 'imports_status_check');
});

test('a ready import without draft is refused', function () {
    expect(fn () => insertImport(['status' => 'ready', 'draft' => null]))
        ->toThrow(QueryException::class, 'imports_ready_draft_check');
});

test('two imports cannot share a round', function () {
    $round = Round::factory()->create();
    insertImport(['round_id' => $round->id]);

    expect(fn () => insertImport(['round_id' => $round->id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('deleting a round deletes its import', function () {
    $round = Round::factory()->create();
    $other = Import::factory()->create();
    insertImport(['round_id' => $round->id]);

    $round->delete();

    expect(Import::query()->pluck('id')->all())->toBe([$other->id]);
});

test('a negative cost is refused in ai_usage', function () {
    expect(fn () => AiUsage::factory()->create(['cost_micro_usd' => -1]))
        ->toThrow(QueryException::class, 'ai_usage_cost_micro_usd_check');
});
