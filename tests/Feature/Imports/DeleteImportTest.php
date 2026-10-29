<?php

/*
 * Spec scorecard-import « Suppression d'un import », « Import introuvable »; design D6.
 *
 * Mutations that turn these tests red:
 * - the photo erased before the commit (Storage delete in the transaction, without
 *   DB::afterCommit): the rolled back deletion loses the photo of an import that still exists;
 * - the refusal of an import being read removed: the import being read is deleted;
 * - the refusal of a saved import removed: the cascade is not involved, the import is deleted
 *   and the photo of its round erased;
 * - the photo route without missing(): the photo of a deleted import answers the bare 404
 *   page instead of « Photo introuvable ».
 */

use App\Domain\Imports\Actions\DeleteImport;
use App\Integrations\Ai\ScorecardPhotoReader;
use App\Jobs\ReadScorecardImport;
use App\Models\AiUsage;
use App\Models\Import;
use App\Models\Round;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('photos');
    $this->withPasscodeSession();
});

test('a failed import is deleted with its photo', function () {
    $import = Import::factory()->failed('Lecture IA indisponible')->withPhoto()->create();

    $this->delete("/imports/{$import->id}")->assertRedirect('/import');

    expect(Import::query()->count())->toBe(0)
        ->and(Storage::disk('photos')->exists($import->photo_path))->toBeFalse();
    $this->get("/imports/{$import->id}/photo")
        ->assertNotFound()
        ->assertExactJson(['error' => 'Photo introuvable']);
});

test('a queued import deleted is never read', function () {
    config(['ai.providers.anthropic.key' => 'test-key']);
    $import = Import::factory()->queued()->withPhoto()->create();

    $this->delete("/imports/{$import->id}")->assertRedirect('/import');
    ReadScorecardImport::dispatch($import->id);

    ScorecardPhotoReader::assertNeverPrompted();
    expect(Import::query()->count())->toBe(0)
        ->and(AiUsage::query()->count())->toBe(0);
});

test('an import being read is not deleted', function () {
    $import = Import::factory()->reading()->withPhoto()->create();

    $this->withHeader('X-Inertia', 'true')
        ->delete("/imports/{$import->id}")
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Lecture en cours']);

    expect(Import::query()->pluck('id')->all())->toBe([$import->id])
        ->and(Storage::disk('photos')->exists($import->photo_path))->toBeTrue();
});

test('a saved import is not deleted', function () {
    $round = Round::factory()->withPhoto()->create();
    $import = Import::factory()->savedAs($round)->create();

    $this->withHeader('X-Inertia', 'true')
        ->delete("/imports/{$import->id}")
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Cette carte est déjà enregistrée']);

    expect($import->fresh()?->round_id)->toBe($round->id)
        ->and(Round::query()->pluck('id')->all())->toBe([$round->id])
        ->and(Storage::disk('photos')->exists($round->photo_path))->toBeTrue();
});

test('the photo is erased only once the deletion is committed', function () {
    $import = Import::factory()->failed('Lecture IA indisponible')->withPhoto()->create();

    try {
        DB::transaction(function () use ($import): void {
            app(DeleteImport::class)->handle($import);

            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    expect(Import::query()->pluck('id')->all())->toBe([$import->id])
        ->and(Storage::disk('photos')->exists($import->photo_path))->toBeTrue();
});
