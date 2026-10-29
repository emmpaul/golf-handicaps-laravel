<?php

namespace App\Jobs;

use App\Domain\AiUsage\AiBudget;
use App\Domain\AiUsage\AiPricing;
use App\Domain\Courses\Actions\FinishCourseRatingLookup;
use App\Domain\Courses\Actions\StartCourseRatingLookup;
use App\Domain\Courses\CourseRatingLookupOutcome;
use App\Domain\Courses\CourseRatingLookupTarget;
use App\Domain\Courses\Sgt\SgtCourse;
use App\Domain\Courses\Sgt\SgtCourseMatcher;
use App\Domain\Imports\AiReading;
use App\Integrations\Ai\SgtScorecardReader;
use App\Integrations\Sgt\SgtClient;
use App\Integrations\Sgt\SgtUnavailable;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Looks up the rating of a course and tees on Simulator Golf Tour (design D5): the SGT list,
 * the match at the same par, the reading of the SGT scorecard by the AI, then the tee of the
 * round. It never writes a rating: only a confirmation does. An SGT error and a transient error
 * of the AI are thrown back for the queue to retry once; failed() then ends the row in failure.
 */
class LookUpCourseRating implements ShouldQueue
{
    use Queueable;

    public const string LOOKUP_OFF = 'Recherche SGT désactivée';

    public const string NO_ROUND = 'Aucun round sur ce parcours';

    public const string AI_DISABLED = 'Lecture IA désactivée';

    public const string BUDGET_REACHED = 'Budget IA du mois atteint';

    public const string AI_UNUSABLE = 'Lecture IA impossible';

    public const string PAR_DIFFERS = 'Par de la carte SGT différent';

    public const string SGT_UNREACHABLE = 'SGT injoignable';

    public int $tries = 2;

    public int $backoff = 30;

    /** Under the --timeout=420 of the worker: a silent SGT never freezes it (spec worker-health). */
    public int $timeout = 120;

    public function __construct(public readonly string $courseRatingId) {}

    /**
     * @throws SgtUnavailable
     */
    public function handle(StartCourseRatingLookup $start, FinishCourseRatingLookup $finish, SgtClient $sgt, AiBudget $budget): void
    {
        $target = $start->handle($this->courseRatingId);
        if ($target === null) {
            return;
        }

        $finish->handle($target, $this->lookUp($target, $sgt, $budget));
    }

    /**
     * Both attempts ended on an error, or the job timed out.
     */
    public function failed(?Throwable $exception): void
    {
        // The target is read again: failed() runs on a fresh copy of the job, without the target
        // of its last attempt. A row no longer pending keeps what the user did.
        $target = app(StartCourseRatingLookup::class)->handle($this->courseRatingId);
        if ($target !== null) {
            app(FinishCourseRatingLookup::class)->handle($target, CourseRatingLookupOutcome::failed(self::SGT_UNREACHABLE));
        }
    }

    /**
     * @throws SgtUnavailable
     */
    private function lookUp(CourseRatingLookupTarget $target, SgtClient $sgt, AiBudget $budget): CourseRatingLookupOutcome
    {
        if (config('golf.sgt.lookup') === 'off') {
            return CourseRatingLookupOutcome::manual(self::LOOKUP_OFF);
        }

        // A round deleted or renamed since: neither an SGT failure nor a reason to retry.
        if ($target->par === null) {
            return CourseRatingLookupOutcome::manual(self::NO_ROUND);
        }

        // A candidate chosen by the user skips the match: its name and place are already written.
        if ($target->sgtCourseId !== null) {
            return $this->propose($target->sgtCourseId, null, $target, $target->par, $sgt, $budget);
        }

        $match = SgtCourseMatcher::match($sgt->courses(), $target->courseName, $target->par);
        if ($match->course !== null) {
            return $this->propose($match->course->id, $match->course, $target, $target->par, $sgt, $budget);
        }

        return $match->candidates === []
            ? CourseRatingLookupOutcome::notFound()
            : CourseRatingLookupOutcome::chooseCourse($match->candidates);
    }

    /**
     * Reads the scorecard of the SGT course, then proposes the tee of the round. A null course
     * keeps the one already written on the row.
     *
     * @throws SgtUnavailable
     */
    private function propose(int $sgtCourseId, ?SgtCourse $course, CourseRatingLookupTarget $target, int $par, SgtClient $sgt, AiBudget $budget): CourseRatingLookupOutcome
    {
        if (! AiReading::enabled()) {
            return CourseRatingLookupOutcome::manual(self::AI_DISABLED, $course);
        }

        $model = config()->string('golf.ai.model');
        $maxCost = AiPricing::cost($model, config()->integer('golf.ai.rating_max_input_tokens'), config()->integer('golf.ai.rating_max_output_tokens'));
        if (! $budget->canSpend(CarbonImmutable::now(), $maxCost)) {
            return CourseRatingLookupOutcome::manual(self::BUDGET_REACHED, $course);
        }

        $reading = (new SgtScorecardReader)->read($sgt->scorecard($sgtCourseId));

        // Billed whatever the answer: counted before anything else is decided.
        $budget->record($model, $reading->usage->inputTokens, $reading->usage->outputTokens, CarbonImmutable::now());

        if ($reading->card === null) {
            return CourseRatingLookupOutcome::manual(self::AI_UNUSABLE, $course);
        }
        if ($reading->card->par !== $par) {
            return CourseRatingLookupOutcome::manual(self::PAR_DIFFERS, $course);
        }

        $tee = SgtCourseMatcher::matchTee($reading->card->tees, $target->tees);

        return $tee === null
            ? CourseRatingLookupOutcome::chooseTee($course, $reading->card->tees)
            : CourseRatingLookupOutcome::proposed($course, $reading->card->tees, $tee->name);
    }
}
