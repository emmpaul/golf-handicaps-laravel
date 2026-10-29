<?php

/*
 * Mutation that turns this test red: a label reworded or moved to another status, as
 * « Confirmée » in the feminine instead of « Confirmé ».
 */

use App\Domain\Courses\CourseRatingStatus;

test('each status of a course rating has its label', function () {
    expect(array_map(fn (CourseRatingStatus $status): array => [$status->value, $status->label()], CourseRatingStatus::cases()))
        ->toBe([
            ['pending', 'Recherche en cours'],
            ['proposed', 'Proposition à confirmer'],
            ['choose_course', 'Parcours SGT à choisir'],
            ['choose_tee', 'Tee à choisir'],
            ['manual', 'Saisie manuelle'],
            ['not_found', 'Introuvable sur SGT'],
            ['failed', 'Recherche échouée'],
            ['confirmed', 'Confirmé'],
        ]);
});
