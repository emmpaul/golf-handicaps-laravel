<?php

/*
 * Spec course-ratings « Parcours SGT et proposition sur la page Parcours »; design D2.
 *
 * Mutations that turn these tests red:
 * - the edge dashes kept: « 401 Par Golf, Par 3 Course. » gives `401-par-golf-par-3-course-`;
 * - the course id left out of the scorecard path, or `?v=1.1` added to it.
 */

use App\Domain\Courses\Sgt\SgtLinks;

test('the scorecard image of an SGT course is named by its id', function () {
    expect(SgtLinks::scorecardUrl(30))
        ->toBe('https://simulatorgolftour.com/public/assets/courseImages/scorecards/scorecard_30.jpg');
});

test('the The Course View page is the slug of the SGT name', function (string $name, string $url) {
    expect(SgtLinks::courseViewUrl($name))->toBe($url);
})->with([
    'plain name' => ['Bethpage Black', 'https://thecourseview.com/courses/bethpage-black'],
    'ampersand' => ['Diamond Hawk Golf Course & Pub', 'https://thecourseview.com/courses/diamond-hawk-golf-course-pub'],
    'trailing punctuation' => ['401 Par Golf, Par 3 Course.', 'https://thecourseview.com/courses/401-par-golf-par-3-course'],
]);
