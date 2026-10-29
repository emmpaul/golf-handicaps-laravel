<?php

/*
 * Design D3: the course list of Simulator Golf Tour is the JSON block `courses-data` of its
 * /courses page; a row out of shape makes the whole page unreadable, as the zod schema of the
 * Next application. Read on tests/fixtures/sgt/courses.html, the page reduced to 14 courses.
 *
 * Mutations that turn these tests red:
 * - a row out of shape skipped instead of making the page unreadable: the page with the par
 *   « "72" » gives 13 courses instead of throwing;
 * - double quotes only accepted around `courses-data` in the expression: the page as served,
 *   with single quotes, has no block;
 * - the cardUrl not checked: the page whose cardUrl lacks {COURSEID} is read.
 */

use App\Domain\Courses\Sgt\SgtCourse;
use App\Integrations\Sgt\SgtCoursesPage;
use App\Integrations\Sgt\SgtUnavailable;

function sgtCoursesFixture(): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/fixtures/sgt/courses.html');
}

test('the page gives its 14 courses in the order of the page', function () {
    $courses = SgtCoursesPage::parse(sgtCoursesFixture());

    expect(array_map(fn (SgtCourse $course): int => $course->id, $courses))
        ->toBe([2341, 1841, 30, 3216, 3222, 573, 574, 477, 3291, 1873, 1969, 1951, 3466, 2184])
        ->and($courses[0])->toEqual(new SgtCourse(2341, '3 Ponds Farm', 'Bridgehampton, New York, USA', 71));
});

test('a row without difficulty is read', function () {
    expect(SgtCoursesPage::parse(sgtCoursesFixture())[1])
        ->toEqual(new SgtCourse(1841, '401 Par Golf, Par 3 Course.', 'Raleigh, NC', 63));
});

test('a page in another format is unreadable', function (Closure $alter, string $message) {
    expect(fn () => SgtCoursesPage::parse($alter(sgtCoursesFixture())))
        ->toThrow(SgtUnavailable::class, $message);
})->with([
    'no courses-data block' => [fn (string $page): string => '<html><body>Maintenance</body></html>', 'The SGT page has no course list'],
    'truncated JSON' => [fn (string $page): string => str_replace(']]}</script>', ']</script>', $page), 'The SGT course list is unreadable'],
    'cardUrl without {COURSEID}' => [fn (string $page): string => str_replace('scorecard_{COURSEID}', 'scorecard_30', $page), 'The SGT course list is unreadable'],
    'par written as text' => [fn (string $page): string => str_replace('[573,"Paynes Valley","Hollister, MO",72,', '[573,"Paynes Valley","Hollister, MO","72",', $page), 'The SGT course list is unreadable'],
]);
