<?php

namespace App\Domain\Courses\Sgt;

use Normalizer;

/**
 * Finds the SGT course and the tee of a round (spec course-ratings, « Correspondance sur la
 * liste Simulator Golf Tour » and « Proposition lue sur la carte SGT »), the port of
 * normalizeCourseName, matchSgtCourse, teeKey and matchTee of the Next application. Done in
 * memory: it compares a round name with the SGT list, never two rows of the base.
 */
final class SgtCourseMatcher
{
    private const int MAX_CANDIDATES = 10;

    /**
     * The comparable name: lower case, without accents, `&` read `and`, punctuation turned into
     * spaces, spaces reduced, without a leading « the ». « THE SANCTUARY » → `sanctuary`.
     */
    public static function normalizeName(string $name): string
    {
        $decomposed = Normalizer::normalize($name, Normalizer::FORM_D);
        $name = (string) preg_replace('/\p{M}/u', '', $decomposed === false ? $name : $decomposed);
        $name = str_replace('&', ' and ', mb_strtolower($name));
        $name = trim((string) preg_replace('/[^a-z0-9]+/', ' ', $name));

        return (string) preg_replace('/^the /', '', $name);
    }

    /**
     * The SGT course of a round of this name and par total: a single equal name at the same par
     * is retained. Several equal names, or else the names that contain each other at the same
     * par, ten at most in the order of the list, are offered.
     *
     * @param  list<SgtCourse>  $courses
     */
    public static function match(array $courses, string $courseName, int $par): SgtMatch
    {
        $wanted = self::normalizeName($courseName);
        $samePar = array_values(array_filter($courses, fn (SgtCourse $course): bool => $course->par === $par));

        $exact = array_values(array_filter(
            $samePar,
            fn (SgtCourse $course): bool => self::normalizeName($course->name) === $wanted,
        ));
        if (count($exact) === 1) {
            return SgtMatch::retained($exact[0]);
        }

        $candidates = $exact !== [] ? $exact : array_values(array_filter($samePar, function (SgtCourse $course) use ($wanted): bool {
            $name = self::normalizeName($course->name);

            return str_contains($name, $wanted) || str_contains($wanted, $name);
        }));

        return $candidates === []
            ? SgtMatch::none()
            : SgtMatch::candidates(array_slice($candidates, 0, self::MAX_CANDIDATES));
    }

    /**
     * The comparable tees: without a trailing distance (« White 6023yd » → `white`), spaces nor
     * case.
     */
    public static function teeKey(string $tees): string
    {
        $tees = (string) preg_replace('/\s*\d+\s*(yd|yds|y|m)$/i', '', trim($tees));

        return mb_strtolower((string) preg_replace('/\s+/', '', $tees));
    }

    /**
     * The tee read on the card that bears the name of the round tees, or null.
     *
     * @param  list<SgtTee>  $teesRead
     */
    public static function matchTee(array $teesRead, string $tees): ?SgtTee
    {
        $wanted = self::teeKey($tees);

        foreach ($teesRead as $tee) {
            if (self::teeKey($tee->name) === $wanted) {
                return $tee;
            }
        }

        return null;
    }
}
