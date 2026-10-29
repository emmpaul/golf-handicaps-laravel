<?php

/*
 * Spec course-ratings « Confirmation d'une proposition », « Choix d'un tee lu », « Actions de
 * recherche refusées »; design D7.
 *
 * Mutations that turn these tests red:
 * - no recompute after the confirmation: Gab and Manu stay at 10 and 170, Manu's index at 170;
 *   after the choice of the tee White, the Gold card stays at 10 and 170 instead of −17 and 105;
 * - the confirmation written with source manual: the row reads manual instead of sgt;
 * - the tee looked up whatever its case (strcasecmp): « white » is accepted instead of refused
 *   with « Tee absent de la carte SGT »;
 * - the NoProposalToConfirm refusal removed: the row of CALA NOVA, without proposed tee, raises
 *   no refusal;
 * - the note cleared by the confirmation (decision Q18): the note « Ancienne note » is lost;
 * - a pending row not refused by the confirmation: the row relaunched after its confirmation is
 *   confirmed again from the proposal of the earlier lookup, and the relaunch is lost.
 */

use App\Domain\Courses\Actions\ChooseCourseRatingTee;
use App\Domain\Courses\Actions\ConfirmCourseRatingProposal;
use App\Domain\Courses\CourseRatingStatus;
use App\Domain\Courses\Exceptions\NoProposalToConfirm;
use App\Domain\Courses\Exceptions\TeeNotRead;
use App\Models\CourseRating;

/**
 * The confirmed values of a row: [rating_t, slope, source, status, proposed_tee, note].
 *
 * @return list<mixed>
 */
function confirmedValues(CourseRating $rating): array
{
    $rating->refresh();

    return [$rating->rating_t, $rating->slope, $rating->source, $rating->status, $rating->proposed_tee, $rating->note];
}

beforeEach(function () {
    createPlayersOnTest('Gab', 'Manu');
    createRoundCard(referenceCardData($this->gab->id, $this->manu->id));
    // The row of the lookup of the card (SGT_LOOKUP off) gives way to the state under test.
    CourseRating::query()->delete();
});

test('confirming the proposal writes 71,4 / 144 from SGT and recomputes Gab and Manu', function () {
    $rating = CourseRating::factory()->proposed()->create(['note' => 'Ancienne note']);
    expect(differentialsByName())->toBe(['Gab' => 10, 'Manu' => 170]);

    app(ConfirmCourseRatingProposal::class)->handle($rating);

    expect(confirmedValues($rating))->toBe([714, 144, 'sgt', CourseRatingStatus::Confirmed, 'Red', 'Ancienne note'])
        ->and(differentialsByName())->toBe(['Gab' => 5, 'Manu' => 130]);
});

test('the confirmation recomputes the index of Manu on his earlier round', function () {
    // Manu 88 on 2026-10-10 on another course of par 71, without rating: 17,0.
    $earlier = referenceCardOn('2026-10-10', [[$this->manu->id, strokesOverPar(17)]]);
    $earlier['courseName'] = 'CALA NOVA';
    $earlier['tees'] = 'White';
    createRoundCard($earlier);
    $rating = CourseRating::factory()->proposed()->create();

    app(ConfirmCourseRatingProposal::class)->handle($rating);

    expect(statsByDateOf($this->manu, ['differential_t', 'index_after_t']))->toBe([[170, 170], [130, 150]]);
});

test('choosing the tee White writes 74,2 / 148 and retains it', function () {
    $rating = CourseRating::factory()->chooseTee()->create();

    app(ChooseCourseRatingTee::class)->handle($rating, 'White');

    expect(confirmedValues($rating))->toBe([742, 148, 'sgt', CourseRatingStatus::Confirmed, 'White', null]);
});

test('retaining the tee White recomputes the rounds of BETHPAGE BLACK / Gold on 74,2 and 148', function () {
    // The reference card again a week later on the Gold tees: Gab 72 and Manu 88, par 71.
    // 113 / 148 × (72 − 74,2) = −1,68 → −1,7; 113 / 148 × (88 − 74,2) = 10,54 → 10,5. The Red
    // card, without rating, stays at 1,0 and 17,0.
    $gold = referenceCardData($this->gab->id, $this->manu->id);
    $gold['tees'] = 'Gold';
    $gold['playedOn'] = '2026-10-24';
    createRoundCard($gold);
    CourseRating::query()->delete();
    $rating = CourseRating::factory()->chooseTee()->create(['course_name' => 'BETHPAGE BLACK', 'tees' => 'Gold']);

    app(ChooseCourseRatingTee::class)->handle($rating, 'White');

    expect(statsByDateOf($this->gab, ['differential_t']))->toBe([[10], [-17]])
        ->and(statsByDateOf($this->manu, ['differential_t']))->toBe([[170], [105]]);
});

test('a confirmation without proposal is refused and nothing changes', function () {
    $rating = CourseRating::factory()->notFound()->create(['course_name' => 'BETHPAGE BLACK', 'tees' => 'Red']);
    $stats = allStats();

    expect(fn () => app(ConfirmCourseRatingProposal::class)->handle($rating))
        ->toThrow(NoProposalToConfirm::class, 'Aucune proposition à confirmer');

    expect(confirmedValues($rating))->toBe([null, null, null, CourseRatingStatus::NotFound, null, null])
        ->and(allStats())->toBe($stats);
});

test('a tee absent from the tees read is refused and nothing changes', function (string $tee) {
    $rating = CourseRating::factory()->chooseTee()->create();
    $stats = allStats();

    expect(fn () => app(ChooseCourseRatingTee::class)->handle($rating, $tee))
        ->toThrow(TeeNotRead::class, 'Tee absent de la carte SGT');

    expect(confirmedValues($rating))->toBe([null, null, null, CourseRatingStatus::ChooseTee, null, null])
        ->and(allStats())->toBe($stats);
})->with(['absent' => ['Gold'], 'another case' => ['white']]);

describe('on a row relaunched after its confirmation', function () {
    beforeEach(function () {
        // Pending again, SGT course forgotten; the proposal and the tees of the earlier lookup
        // are still on the row while the new lookup runs.
        $this->rating = CourseRating::factory()->confirmedFromSgt()->create([
            'status' => CourseRatingStatus::Pending,
            'sgt_course_id' => null, 'sgt_course_name' => null, 'sgt_course_location' => null,
        ]);
        $this->stats = allStats();
    });

    test('confirming the earlier proposal is refused and nothing changes', function () {
        expect(fn () => app(ConfirmCourseRatingProposal::class)->handle($this->rating))
            ->toThrow(NoProposalToConfirm::class, 'Aucune proposition à confirmer');

        expect(confirmedValues($this->rating))->toBe([714, 144, 'sgt', CourseRatingStatus::Pending, 'Red', null])
            ->and(allStats())->toBe($this->stats);
    });

    test('retaining an earlier tee read is refused and nothing changes', function () {
        expect(fn () => app(ChooseCourseRatingTee::class)->handle($this->rating, 'White'))
            ->toThrow(TeeNotRead::class, 'Tee absent de la carte SGT');

        expect(confirmedValues($this->rating))->toBe([714, 144, 'sgt', CourseRatingStatus::Pending, 'Red', null])
            ->and(allStats())->toBe($this->stats);
    });
});
