/**
 * Strokes received in a match (spec `match-strokes`, design D5), computed in the
 * browser from integer tenths, as `src/domain/strokes.ts` of the Next.js
 * application. The course handicap mirrors `Whs::courseHandicap` in PHP;
 * tests/fixtures/course-handicap.json keeps the two aligned.
 */

/** Playing handicap: the index (in tenths) rounded to the integer, 0,5 up. */
export function playingHandicap(indexT: number): number {
    return Math.floor((indexT + 5) / 10);
}

/** Rating (tenths) and slope of a course, with its total par. */
export type RatedCourse = {
    ratingT: number;
    slope: number;
    par: number;
};

/**
 * Course handicap: index × slope / 113 + (course rating − par), rounded to the
 * integer, 0,5 up. Without rating: the rounded index (`playingHandicap`).
 */
export function courseHandicap(indexT: number, course?: RatedCourse): number {
    if (course === undefined) {
        return playingHandicap(indexT);
    }

    const { ratingT, slope, par } = course;

    return Math.floor(((indexT * slope) / 113 + ratingT - par * 10) / 10 + 0.5);
}

const HOLES = 18;

/**
 * Strokes per hole for S strokes: ⌊S / 18⌋ everywhere, plus 1 on the holes whose
 * index is ≤ S mod 18. For −S, gives back 1 stroke (−1) on the S holes with the
 * highest indexes.
 */
export function allocateStrokes(
    strokes: number,
    strokeIndexes: readonly number[],
): number[] {
    if (strokes < 0) {
        return allocateStrokes(
            -strokes,
            strokeIndexes.map((index) => HOLES + 1 - index),
        ).map((count) => (count === 0 ? 0 : -count));
    }

    const base = Math.floor(strokes / HOLES);
    const extra = strokes % HOLES;

    return strokeIndexes.map((index) => base + (index <= extra ? 1 : 0));
}

export type StrokeMode = 'ecart' | 'complet';

/**
 * Strokes received by each player. « Complet »: the whole playing handicap.
 * « Écart » (default): the gap to the lowest playing handicap of the match.
 */
export function matchStrokes(
    indexesT: readonly number[],
    mode: StrokeMode = 'ecart',
    course?: RatedCourse,
): number[] {
    const handicaps = indexesT.map((indexT) => courseHandicap(indexT, course));

    if (mode === 'complet') {
        return handicaps;
    }

    const lowest = Math.min(...handicaps);

    return handicaps.map((handicap) => handicap - lowest);
}
