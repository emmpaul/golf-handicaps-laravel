<?php

/*
 * Spec scorecard-ai-completion (« Une lecture par l'IA par import », « Données envoyées à
 * l'IA », « Lecture désactivée sans clé d'API », « Erreur de l'IA », « Coût de chaque appel
 * compté », « Plafond de dépense mensuel »); spec scorecard-import « Échec de la lecture »;
 * design D3, D4, D9. The agent is faked: no test reaches Anthropic.
 *
 * Mutations that turn these tests red:
 * - the usage counted after the parse (only for a draft): the billed answer out of shape
 *   leaves no ai_usage row;
 * - the budget checked after the call: the reading at the ceiling is prompted;
 * - StartReading without the status check: the job run twice on a ready import prompts twice;
 * - $tries = 1 on the job: the first transient error through the queue already fails the
 *   import; $tries = 3: the second error leaves it being read;
 * - StartReading without the round_id check: a queued import already linked to a round is
 *   prompted, counted and turned ready;
 * - the model written in the agent instead of config('golf.ai.model'): claude-opus-5-5 is not
 *   the model asked;
 * - the date text not read by SimulatorDate: playedOn stays null in the draft.
 */

use App\Domain\Imports\ImportStatus;
use App\Integrations\Ai\ScorecardPhotoReader;
use App\Jobs\ReadScorecardImport;
use App\Models\AiUsage;
use App\Models\Import;
use App\Models\Player;
use App\Models\Round;
use Carbon\CarbonImmutable;
use Database\Factories\ImportFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Files\LocalImage;

/**
 * The rows of ai_usage, without their ids and dates.
 *
 * @return list<array<string, mixed>>
 */
function aiUsageRows(): array
{
    return AiUsage::query()->orderBy('created_at')
        ->get(['month', 'model', 'input_tokens', 'output_tokens', 'cost_micro_usd'])
        ->toArray();
}

/**
 * The import failed with « Lecture IA indisponible », no call counted.
 */
function expectUnavailableWithoutCost(Import $import): void
{
    expect($import->fresh()->status)->toBe(ImportStatus::Failed)
        ->and($import->fresh()->failure)->toBe('Lecture IA indisponible')
        ->and(AiUsage::query()->count())->toBe(0);
}

function runReadingJob(ReadScorecardImport $job): void
{
    app()->call([$job, 'handle']);
}

beforeEach(function () {
    Storage::fake('photos');
    config(['ai.providers.anthropic.key' => 'test-key']);
    $this->travelTo(CarbonImmutable::parse('2026-10-20 15:00:00', 'UTC'));
    $this->import = Import::factory()->withPhoto()->create();
});

test('a reading turns the import ready with the draft read, counts its cost and sends only the photo', function () {
    createPlayersOnTest('Gab', 'Manu', 'Sean');
    $sent = [];
    ScorecardPhotoReader::fake(function (string $prompt, Collection $attachments, $provider, string $model) use (&$sent) {
        // Read during the call, while the temporary JPEG still exists.
        $sent = [
            'prompt' => $prompt,
            'model' => $model,
            'attachments' => $attachments->map(fn ($file): string => $file::class)->all(),
            'size' => array_slice((array) getimagesize($attachments->first()->path), 0, 2),
            'mime' => getimagesize($attachments->first()->path)['mime'] ?? null,
        ];

        return billedAnswer(referenceReading());
    });

    ReadScorecardImport::dispatch($this->import->id);

    $import = $this->import->fresh();
    expect($import->status)->toBe(ImportStatus::Ready)
        ->and($import->failure)->toBeNull()
        ->and($import->draft)->toEqual(ImportFactory::referenceDraft())
        ->and($import->draft['playedOn'])->toBe('2026-10-17')
        ->and(aiUsageRows())->toBe([[
            'month' => '2026-10',
            'model' => 'claude-sonnet-5-5',
            'input_tokens' => 3000,
            'output_tokens' => 1200,
            'cost_micro_usd' => 18000,
        ]])
        ->and($sent['model'])->toBe('claude-sonnet-5-5')
        ->and($sent['attachments'])->toBe([LocalImage::class])
        ->and($sent['size'])->toBe([1568, 1176])
        ->and($sent['mime'])->toBe('image/jpeg');

    foreach (['Gab', 'Manu', 'Sean'] as $name) {
        expect($sent['prompt'])->not->toContain($name)
            ->and((new ScorecardPhotoReader)->instructions())->not->toContain($name);
    }
    expect(Player::query()->count())->toBe(3);
    ScorecardPhotoReader::assertPromptedTimes(1);
});

test('the model of AI_MODEL is asked and priced', function () {
    config(['golf.ai.model' => 'claude-opus-5-5']);
    ScorecardPhotoReader::fake([billedAnswer(referenceReading())]);

    ReadScorecardImport::dispatch($this->import->id);

    ScorecardPhotoReader::assertPrompted(fn ($prompt): bool => $prompt->model === 'claude-opus-5-5');
    expect(aiUsageRows()[0]['model'])->toBe('claude-opus-5-5')
        ->and(aiUsageRows()[0]['cost_micro_usd'])->toBe(36000);
});

test('at the monthly ceiling the import fails without any call', function () {
    AiUsage::factory()->inMonth('2026-10')->create(['cost_micro_usd' => 4960000]);

    ReadScorecardImport::dispatch($this->import->id);

    expect($this->import->fresh()->status)->toBe(ImportStatus::Failed)
        ->and($this->import->fresh()->failure)->toBe('Budget IA du mois atteint')
        ->and(AiUsage::query()->count())->toBe(1);
    ScorecardPhotoReader::assertNeverPrompted();
});

test('without an API key the import fails without any call', function () {
    config(['ai.providers.anthropic.key' => '']);

    ReadScorecardImport::dispatch($this->import->id);

    expect($this->import->fresh()->status)->toBe(ImportStatus::Failed)
        ->and($this->import->fresh()->failure)->toBe('Lecture des photos désactivée : aucune clé d’IA n’est configurée.');
    ScorecardPhotoReader::assertNeverPrompted();
});

test('an import deleted before its reading is never sent', function () {
    $id = $this->import->id;
    $this->import->delete();

    ReadScorecardImport::dispatch($id);

    ScorecardPhotoReader::assertNeverPrompted();
    expect(AiUsage::query()->count())->toBe(0);
});

test('a queued import already linked to a round is never sent', function () {
    $round = Round::factory()->create();
    $this->import->update(['round_id' => $round->id]);
    ScorecardPhotoReader::fake([billedAnswer(referenceReading())]);

    ReadScorecardImport::dispatch($this->import->id);

    ScorecardPhotoReader::assertNeverPrompted();
    expect($this->import->fresh()->status)->toBe(ImportStatus::Queued)
        ->and(AiUsage::query()->count())->toBe(0);
});

test('a job run again on a ready import makes no second call', function () {
    ScorecardPhotoReader::fake([billedAnswer(referenceReading()), billedAnswer(referenceReading())]);

    ReadScorecardImport::dispatch($this->import->id);
    ReadScorecardImport::dispatch($this->import->id);

    ScorecardPhotoReader::assertPromptedTimes(1);
    expect($this->import->fresh()->status)->toBe(ImportStatus::Ready)
        ->and(AiUsage::query()->count())->toBe(1);
});

test('a billed answer without a usable draft is counted and fails the import without retry', function (array $card) {
    ScorecardPhotoReader::fake([billedAnswer($card)]);
    $job = new ReadScorecardImport($this->import->id);

    // No exception: the queue would retry it.
    runReadingJob($job);

    expect($this->import->fresh()->status)->toBe(ImportStatus::Failed)
        ->and($this->import->fresh()->failure)->toBe('Lecture IA indisponible')
        ->and($this->import->fresh()->draft)->toBeNull()
        ->and(aiUsageRows())->toBe([[
            'month' => '2026-10',
            'model' => 'claude-sonnet-5-5',
            'input_tokens' => 3000,
            'output_tokens' => 1200,
            'cost_micro_usd' => 18000,
        ]]);
    ScorecardPhotoReader::assertPromptedTimes(1);
})->with([
    'a line of 17 scores' => [function (): array {
        $card = referenceReading();
        $card['players'][0]['scores']['holes'] = [5, 5, 3, 5, 4, 4, 4, 3, 5, 4, 4, 4, 4, 3, 4, 5, 3];

        return $card;
    }],
    // A refusal or a truncated output: the SDK returns an empty card with the billed usage.
    'a refusal' => [fn (): array => []],
]);

test('a transient error then a draft makes the import ready', function () {
    $calls = 0;
    ScorecardPhotoReader::fake(function () use (&$calls) {
        if (++$calls === 1) {
            throw ProviderOverloadedException::forProvider('anthropic');
        }

        return billedAnswer(referenceReading());
    });
    $job = new ReadScorecardImport($this->import->id);

    expect(fn () => runReadingJob($job))->toThrow(ProviderOverloadedException::class);
    expect(AiUsage::query()->count())->toBe(0);

    runReadingJob($job);

    expect($this->import->fresh()->status)->toBe(ImportStatus::Ready)
        ->and(AiUsage::query()->count())->toBe(1);
});

test('a transient error on the last attempt fails the import', function () {
    ScorecardPhotoReader::fake(fn () => throw ProviderOverloadedException::forProvider('anthropic'));
    $job = new ReadScorecardImport($this->import->id);

    try {
        runReadingJob($job);
    } catch (ProviderOverloadedException $exception) {
        $job->failed($exception);
    }

    expectUnavailableWithoutCost($this->import);
});

test('through the queue, a reading is tried twice, then the import fails', function () {
    config(['queue.default' => 'database']);
    ScorecardPhotoReader::fake(fn () => throw ProviderOverloadedException::forProvider('anthropic'));
    ReadScorecardImport::dispatch($this->import->id);

    $this->artisan('queue:work', ['--stop-when-empty' => true, '--sleep' => 0])->assertSuccessful();

    ScorecardPhotoReader::assertPromptedTimes(1);
    expect($this->import->fresh()->status)->toBe(ImportStatus::Reading);

    // Released for its 30 s backoff.
    $this->travel(31)->seconds();
    $this->artisan('queue:work', ['--stop-when-empty' => true, '--sleep' => 0])->assertSuccessful();

    ScorecardPhotoReader::assertPromptedTimes(2);
    expectUnavailableWithoutCost($this->import);
});
