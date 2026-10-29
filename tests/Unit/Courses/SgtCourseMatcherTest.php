<?php

/*
 * Spec course-ratings « Correspondance sur la liste Simulator Golf Tour » and « Proposition lue
 * sur la carte SGT »; design D2. Port of tests/server/courses/sgt.test.ts of the Next
 * application, on the 14 courses of tests/fixtures/sgt/courses.html written here by hand.
 *
 * Mutations that turn these tests red:
 * - the leading `the` kept: « THE SANCTUARY » normalises to `the sanctuary` and finds no
 *   course instead of 477;
 * - the par ignored: « PAYNES VALLEY 19TH » at par 72 retains 574 (par 3) instead of offering
 *   573, and « BETHPAGE BLACK » at par 72 retains 30 (par 71);
 * - a substring kept outright: a single candidate in substring is retained instead of offered;
 * - the distance not removed: « White 6023yd » keys to `white6023yd` and finds no tee.
 */

use App\Domain\Courses\Sgt\SgtCourse;
use App\Domain\Courses\Sgt\SgtCourseMatcher;
use App\Domain\Courses\Sgt\SgtMatch;
use App\Domain\Courses\Sgt\SgtTee;

/**
 * The 14 courses of tests/fixtures/sgt/courses.html, in the order of the page.
 *
 * @return list<SgtCourse>
 */
function fixtureSgtCourses(): array
{
    return [
        new SgtCourse(2341, '3 Ponds Farm', 'Bridgehampton, New York, USA', 71),
        new SgtCourse(1841, '401 Par Golf, Par 3 Course.', 'Raleigh, NC', 63),
        new SgtCourse(30, 'Bethpage Black', 'Farmingdale, NY', 71),
        new SgtCourse(3216, 'Calanova Golf Club', 'La Cala de Mijas, Mallaga, Spain', 72),
        new SgtCourse(3222, 'Diamond Hawk Golf Course & Pub', 'Cheektowaga, New York, USA', 72),
        new SgtCourse(573, 'Paynes Valley', 'Hollister, MO', 72),
        new SgtCourse(574, 'Paynes Valley 19th', 'Hollister, MO', 3),
        new SgtCourse(477, 'Sanctuary', 'Sedalia, CO', 72),
        new SgtCourse(3291, 'Sanctuary GC', 'Sanibel, Florida, USA', 72),
        new SgtCourse(1873, 'Sanctuary Lake', 'Troy, Mi, USA', 71),
        new SgtCourse(1969, 'Sanctuary Moon', 'Endor, Outer Rim, Fictional', 72),
        new SgtCourse(1951, 'The Pines Sanctuary Cove', 'Hope Island, Queensland, Australia', 72),
        new SgtCourse(3466, 'Tualatin Country Club', 'Tualatin, Oregon, USA', 72),
        new SgtCourse(2184, 'Zoar Village G.C.', 'Zoar, Ohio, USA', 72),
    ];
}

/**
 * The match reduced to what a caller reads: the id retained, the ids offered, or 'none'.
 *
 * @return array{retained: int}|array{candidates: list<int>}|'none'
 */
function matchOutcome(SgtMatch $match): array|string
{
    if ($match->course !== null) {
        return ['retained' => $match->course->id];
    }

    return $match->candidates === []
        ? 'none'
        : ['candidates' => array_map(fn (SgtCourse $course): int => $course->id, $match->candidates)];
}

test('a course name is compared without case, accents, punctuation nor a leading The', function (string $name, string $normalised) {
    expect(SgtCourseMatcher::normalizeName($name))->toBe($normalised);
})->with([
    'leading The' => ['THE SANCTUARY', 'sanctuary'],
    'ampersand' => ['Diamond Hawk Golf Course & Pub', 'diamond hawk golf course and pub'],
    'accents' => ['Château Élan', 'chateau elan'],
    'punctuation and spaces' => ['  Golf  de Saint-Cloud  ', 'golf de saint cloud'],
    'The inside the name' => ['Over The Hill', 'over the hill'],
]);

test('a round course finds its SGT course of the same name and par', function (string $courseName, int $par, array|string $outcome) {
    expect(matchOutcome(SgtCourseMatcher::match(fixtureSgtCourses(), $courseName, $par)))->toBe($outcome);
})->with([
    'Bethpage Black' => ['BETHPAGE BLACK', 71, ['retained' => 30]],
    'Paynes Valley, not its par 3' => ['PAYNES VALLEY', 72, ['retained' => 573]],
    'The Sanctuary' => ['THE SANCTUARY', 72, ['retained' => 477]],
    'same name at another par' => ['PAYNES VALLEY 19TH', 72, ['candidates' => [573]]],
    'Bethpage Black at par 72' => ['BETHPAGE BLACK', 72, 'none'],
    'names that contain each other' => ['SANCTUARY COVE', 72, ['candidates' => [477, 1951]]],
    'a single substring is offered, not retained' => ['DIAMOND HAWK', 72, ['candidates' => [3222]]],
    'no course' => ['CALA NOVA', 72, 'none'],
]);

test('the retained course carries its SGT name, location and par', function () {
    expect(SgtCourseMatcher::match(fixtureSgtCourses(), 'BETHPAGE BLACK', 71)->course)
        ->toEqual(new SgtCourse(30, 'Bethpage Black', 'Farmingdale, NY', 71));
});

test('two SGT courses of the same name and par are offered', function () {
    $twins = [new SgtCourse(1, 'Pebble Creek', 'Ohio', 72), new SgtCourse(2, 'PEBBLE CREEK', 'Texas', 72)];

    expect(matchOutcome(SgtCourseMatcher::match($twins, 'Pebble Creek', 72)))->toBe(['candidates' => [1, 2]]);
});

test('at most ten candidates are offered, in the order of the list', function () {
    $eleven = array_map(fn (int $id): SgtCourse => new SgtCourse($id, "Lakeside {$id}", '', 72), range(1, 11));

    expect(matchOutcome(SgtCourseMatcher::match($eleven, 'Lakeside', 72)))->toBe(['candidates' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]]);
});

test('tees are compared without a trailing distance, spaces nor case', function (string $tees, string $key) {
    expect(SgtCourseMatcher::teeKey($tees))->toBe($key);
})->with([
    'yards glued' => ['White 6023yd', 'white'],
    'yards spaced' => ['Blue 6212 yds', 'blue'],
    'metres' => ['Red 5400m', 'red'],
    'inner space' => [' Par 3 ', 'par3'],
]);

test('the tee read on the card that bears the round tees is found', function (string $tees, ?array $found) {
    $read = [
        new SgtTee('Blue', 775, 155),
        new SgtTee('White', 742, 148),
        new SgtTee('Red', 714, 144),
        new SgtTee('Junior', 710, 120),
        new SgtTee('PAR3', 540, 120),
    ];

    $tee = SgtCourseMatcher::matchTee($read, $tees);

    expect($tee === null ? null : [$tee->name, $tee->ratingT, $tee->slope])->toBe($found);
})->with([
    'other case' => ['red', ['Red', 714, 144]],
    'distance in the tees' => ['White 6023yd', ['White', 742, 148]],
    'tee absent from the card' => ['Gold', null],
]);
