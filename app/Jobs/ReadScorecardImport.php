<?php

namespace App\Jobs;

use App\Domain\AiUsage\AiBudget;
use App\Domain\Imports\Actions\FailImport;
use App\Domain\Imports\Actions\MarkImportReady;
use App\Domain\Imports\Actions\StartReading;
use App\Domain\Imports\AiReading;
use App\Domain\Imports\Exceptions\ImportReadingDisabled;
use App\Domain\Imports\ReadingFailure;
use App\Domain\Imports\ScorecardDraft;
use App\Domain\Imports\SimulatorDate;
use App\Integrations\Ai\ScorecardPhotoReader;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Reads the photo of an import with the AI, then writes the result through the Actions of the
 * domain (design D4). A transient error of the API is thrown back for the queue to retry once;
 * a billed answer without a usable draft fails the import at once.
 */
class ReadScorecardImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 30;

    public int $timeout = 120;

    public function __construct(public readonly string $importId) {}

    public function handle(StartReading $startReading, AiBudget $budget, MarkImportReady $markReady, FailImport $failImport): void
    {
        $import = $startReading->handle($this->importId);
        if ($import === null) {
            return;
        }

        if (! AiReading::enabled()) {
            $failImport->handle($this->importId, ImportReadingDisabled::MESSAGE);

            return;
        }

        if (! $budget->canSpend(CarbonImmutable::now())) {
            $failImport->handle($this->importId, ReadingFailure::BUDGET_REACHED);

            return;
        }

        $result = (new ScorecardPhotoReader)->read($import->photo_path);

        // Billed whatever the answer: counted before anything else is decided.
        $budget->record(config()->string('golf.ai.model'), $result->usage->inputTokens, $result->usage->outputTokens, CarbonImmutable::now());

        $draft = ScorecardDraft::fromArray($this->withPlayedOn($result->card));

        $draft === null
            ? $failImport->handle($this->importId, ReadingFailure::AI_UNAVAILABLE)
            : $markReady->handle($this->importId, $draft);
    }

    /**
     * Both attempts ended on an error, or the job timed out.
     */
    public function failed(?Throwable $exception): void
    {
        app(FailImport::class)->handle($this->importId, ReadingFailure::AI_UNAVAILABLE);
    }

    /**
     * The card with the date text read by the server, month/day/year, in place of the text.
     *
     * @param  array<mixed>  $card
     * @return array<mixed>
     */
    private function withPlayedOn(array $card): array
    {
        $dateText = $card['dateText'] ?? null;
        unset($card['dateText']);

        return [...$card, 'playedOn' => SimulatorDate::parse(is_string($dateText) ? $dateText : null)];
    }
}
