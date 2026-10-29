<?php

/*
 * Spec scorecard-import « Relance de la lecture », « Relance refusée », « Liste des imports »
 * (« Relancer la lecture »); spec scorecard-ai-completion « Plafond de dépense mensuel »;
 * design D6, D9. The agent is faked: no test reaches Anthropic.
 *
 * Mutations that turn these tests red:
 * - the photo written again at a new path (copied to imports/<new uuid>.webp): photo_path
 *   changes;
 * - the budget not checked at the request: at the ceiling the job is pushed and the import
 *   turns queued;
 * - `failure` kept by the retry: the queued import still says « Lecture IA indisponible »;
 * - the job pushed before the commit (afterCommit() dropped): the job runs inside the
 *   transaction that is then rolled back;
 * - the refusal of a ready import removed: the ready import turns queued and loses its draft;
 * - the action `retry` listed without checking the key: /import without key offers it.
 */

use App\Domain\Imports\Actions\RetryImportReading;
use App\Domain\Imports\ImportStatus;
use App\Integrations\Ai\ScorecardPhotoReader;
use App\Jobs\ReadScorecardImport;
use App\Models\AiUsage;
use App\Models\Import;
use App\Models\Round;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

function retryReading(Import $import): TestResponse
{
    return test()->withHeader('X-Inertia', 'true')->post("/imports/{$import->id}/reading");
}

/**
 * The columns of an import a refused retry must leave as they were.
 *
 * @return array<string, mixed>
 */
function importState(Import $import): array
{
    $fresh = $import->fresh();

    return [
        'status' => $fresh?->status,
        'failure' => $fresh?->failure,
        'draft' => $fresh?->draft,
        'correction' => $fresh?->correction,
        'photo_path' => $fresh?->photo_path,
        'round_id' => $fresh?->round_id,
    ];
}

beforeEach(function () {
    Storage::fake('photos');
    config(['ai.providers.anthropic.key' => 'test-key']);
    $this->travelTo(CarbonImmutable::parse('2026-10-20 15:00:00', 'UTC'));
    $this->import = Import::factory()->failed('Lecture IA indisponible')->withPhoto()->create([
        'correction' => ['version' => 1, 'card' => referenceCardData(null, null)],
    ]);
});

describe('with a session', function () {
    beforeEach(function () {
        $this->withPasscodeSession();
    });

    test('a failed import is queued again with the same photo, without failure nor correction', function () {
        Queue::fake();
        $photo = Storage::disk('photos')->get($this->import->photo_path);

        retryReading($this->import)
            ->assertRedirect('/import')
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Lecture relancée.']);

        $import = $this->import->fresh();
        expect($import->status)->toBe(ImportStatus::Queued)
            ->and($import->failure)->toBeNull()
            ->and($import->correction)->toBeNull()
            ->and($import->photo_path)->toBe($this->import->photo_path)
            ->and(Storage::disk('photos')->get($import->photo_path))->toBe($photo)
            ->and(Storage::disk('photos')->allFiles())->toBe([$this->import->photo_path]);
        Queue::assertPushed(ReadScorecardImport::class, 1);
        Queue::assertPushed(ReadScorecardImport::class, fn (ReadScorecardImport $job): bool => $job->importId === $this->import->id);
    });

    test('the retried reading makes the import ready and counts its cost', function () {
        ScorecardPhotoReader::fake([billedAnswer(referenceReading())]);

        retryReading($this->import)->assertRedirect('/import');

        expect($this->import->fresh()->status)->toBe(ImportStatus::Ready)
            ->and(AiUsage::query()->pluck('cost_micro_usd')->all())->toBe([18000]);
        ScorecardPhotoReader::assertPromptedTimes(1);
    });

    test('at the monthly ceiling the retry is refused without job nor call', function () {
        Queue::fake();
        AiUsage::factory()->inMonth('2026-10')->create(['cost_micro_usd' => 4960000]);
        $before = importState($this->import);

        retryReading($this->import)
            ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Budget IA du mois atteint']);

        expect(importState($this->import))->toBe($before)
            ->and($before['status'])->toBe(ImportStatus::Failed)
            ->and($before['failure'])->toBe('Lecture IA indisponible');
        Queue::assertNothingPushed();
        ScorecardPhotoReader::assertNeverPrompted();
    });

    test('the ceiling of October does not hold in November', function () {
        Queue::fake();
        AiUsage::factory()->inMonth('2026-10')->create(['cost_micro_usd' => 4960000]);
        $this->travelTo(CarbonImmutable::parse('2026-11-15 15:00:00', 'UTC'));

        retryReading($this->import)->assertRedirect('/import');

        expect($this->import->fresh()->status)->toBe(ImportStatus::Queued);
        Queue::assertPushed(ReadScorecardImport::class, 1);
    });

    test('a retry is refused for an import that is not failed', function (callable $import, string $message) {
        Queue::fake();
        $import = $import();
        $before = importState($import);

        retryReading($import)->assertInertiaFlash('toast', ['type' => 'error', 'message' => $message]);

        expect(importState($import))->toBe($before);
        Queue::assertNothingPushed();
    })->with([
        'ready' => [fn () => Import::factory()->ready()->create(), 'Cet import n’est pas en échec'],
        'queued' => [fn () => Import::factory()->queued()->create(), 'Lecture en cours'],
        'reading' => [fn () => Import::factory()->reading()->create(), 'Lecture en cours'],
        'saved' => [fn () => Import::factory()->savedAs(Round::factory()->create())->create(), 'Cette carte est déjà enregistrée'],
    ]);

    test('without an API key the retry is refused', function () {
        Queue::fake();
        config(['ai.providers.anthropic.key' => '']);
        $before = importState($this->import);

        retryReading($this->import)->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'Lecture des photos désactivée : aucune clé d’IA n’est configurée.',
        ]);

        expect(importState($this->import))->toBe($before);
        Queue::assertNothingPushed();
    });

    test('the list offers the retry of a failed import only with an API key', function (?string $key, array $actions) {
        config(['ai.providers.anthropic.key' => $key]);

        $imports = $this->get('/import')->assertOk()->inertiaProps('imports');

        expect($imports[0]['actions'])->toBe($actions);
    })->with([
        'with a key' => ['test-key', ['correctEmpty', 'retry', 'delete']],
        'without key' => [null, ['correctEmpty', 'delete']],
    ]);
});

test('without a session the retry redirects to /login and changes nothing', function () {
    $before = importState($this->import);

    retryReading($this->import)->assertRedirect('/login');

    expect(importState($this->import))->toBe($before);
});

test('the retried reading leaves only once the import is committed', function () {
    // The sync queue of phpunit.xml, which honours afterCommit: the job would run right away.
    $ran = false;
    Queue::before(function () use (&$ran): void {
        $ran = true;
    });

    try {
        DB::transaction(function (): void {
            app(RetryImportReading::class)->handle($this->import, CarbonImmutable::now());

            throw new LogicException('rolled back');
        });
    } catch (LogicException) {
    }

    expect($ran)->toBeFalse()
        ->and($this->import->fresh()->status)->toBe(ImportStatus::Failed);
});
