<?php

namespace App\Http\Controllers;

use App\Domain\Courses\Actions\ChooseCourseRatingTee;
use App\Domain\Courses\Actions\ChooseSgtCandidate;
use App\Domain\Courses\Actions\ConfirmCourseRatingProposal;
use App\Domain\Courses\Actions\RelaunchCourseRatingLookup;
use App\Http\Requests\ChooseCourseRatingTeeRequest;
use App\Http\Requests\ChooseSgtCandidateRequest;
use App\Models\CourseRating;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * The actions of the lookup of a rating on the Courses page, addressed by the id of its row
 * (design D7). A refusal goes back with its error toast (bootstrap/app.php).
 */
class CourseRatingLookupController extends Controller
{
    private const string CONFIRMED = 'Rating confirmée.';

    private const string RELAUNCHED = 'Recherche relancée.';

    /**
     * Confirm the tee proposed, recompute the players of the course, then go back.
     */
    public function confirm(CourseRating $courseRating, ConfirmCourseRatingProposal $confirm): RedirectResponse
    {
        $confirm->handle($courseRating);

        return $this->back(self::CONFIRMED);
    }

    /**
     * Retain a tee read on the SGT card, recompute the players of the course, then go back.
     */
    public function chooseTee(ChooseCourseRatingTeeRequest $request, CourseRating $courseRating, ChooseCourseRatingTee $chooseTee): RedirectResponse
    {
        $chooseTee->handle($courseRating, $request->string('tee')->value());

        return $this->back(self::CONFIRMED);
    }

    /**
     * Retain an SGT candidate and relaunch the lookup from its card, then go back.
     */
    public function chooseCandidate(ChooseSgtCandidateRequest $request, CourseRating $courseRating, ChooseSgtCandidate $chooseCandidate): RedirectResponse
    {
        $chooseCandidate->handle($courseRating, $request->integer('sgtCourseId'));

        return $this->back(self::RELAUNCHED);
    }

    /**
     * Relaunch the whole lookup, then go back.
     */
    public function relaunch(CourseRating $courseRating, RelaunchCourseRatingLookup $relaunch): RedirectResponse
    {
        $relaunch->handle($courseRating);

        return $this->back(self::RELAUNCHED);
    }

    private function back(string $message): RedirectResponse
    {
        return Inertia::flash('toast', ['type' => 'success', 'message' => $message])->back();
    }
}
