import { describe, expect, it } from 'vite-plus/test';
import {
    allocateStrokes,
    courseHandicap,
    matchStrokes,
    playingHandicap,
    type RatedCourse,
} from '@/lib/strokes';
import { STROKE_INDEXES } from '@/tests/reference-round';
import fixture from '../../../tests/fixtures/course-handicap.json';

// Port of tests/domain/strokes.test.ts of the Next.js application (spec
// match-strokes). tests/fixtures/course-handicap.json is shared with
// tests/Unit/Handicaps/CourseHandicapTest.php: a gap between the TypeScript and
// PHP course handicaps turns one of the two red.
//
// Mutations that turn these tests red:
// - truncation instead of rounding 0,5 up: playingHandicap(125) gives 12;
// - (ratingT − par × 10) dropped from courseHandicap: 1, 22, −3 instead of 2, 23, −2;
// - `index < S mod 18` instead of `≤`: 1 stroke falls nowhere, 16 strokes skip hole 9;
// - strokes given back on the lowest indexes: −2 marks holes 15 and 4;
// - rating ignored by matchStrokes: [0, 16] and [1, 17] on BETHPAGE BLACK / Red;
// - default mode « complet »: matchStrokes([10, 174]) gives [1, 17].

const playingCases = fixture.playingHandicap as {
    indexT: number;
    expected: number;
}[];
const courseCases = fixture.courseHandicap as {
    indexT: number;
    course: RatedCourse | null;
    expected: number;
}[];

/** BETHPAGE BLACK / Red: rating 71,4, slope 144, par 71. */
const bethpageRed = { ratingT: 714, slope: 144, par: 71 };

describe('shared course handicap cases', () => {
    it('reads six playing handicap cases and five course handicap cases', () => {
        expect(playingCases).toHaveLength(6);
        expect(courseCases).toHaveLength(5);
    });

    it.each(playingCases)(
        'playingHandicap($indexT) gives $expected',
        ({ indexT, expected }) => {
            expect(playingHandicap(indexT)).toBe(expected);
        },
    );

    it.each(courseCases)(
        'courseHandicap($indexT, $course) gives $expected',
        ({ indexT, course, expected }) => {
            expect(courseHandicap(indexT, course ?? undefined)).toBe(expected);
        },
    );
});

describe('allocateStrokes on BETHPAGE BLACK / Red', () => {
    it.each([
        {
            strokes: 0,
            perHole: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        },
        {
            strokes: 1,
            perHole: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0],
        },
        {
            strokes: 16,
            perHole: [1, 1, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 1, 1, 1, 1],
        },
        {
            strokes: 18,
            perHole: [1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1],
        },
        {
            strokes: 20,
            perHole: [1, 1, 1, 2, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 2, 1, 1, 1],
        },
        {
            strokes: 36,
            perHole: [2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2],
        },
        {
            strokes: -2,
            perHole: [0, 0, -1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, -1, 0, 0, 0, 0],
        },
    ])('$strokes strokes', ({ strokes, perHole }) => {
        expect(allocateStrokes(strokes, STROKE_INDEXES)).toEqual(perHole);
    });
});

describe('matchStrokes', () => {
    it.each([
        { indexesT: [10, 174], mode: 'ecart', expected: [0, 16] },
        { indexesT: [10, 174], mode: 'complet', expected: [1, 17] },
        { indexesT: [125, 10, 174], mode: 'ecart', expected: [12, 0, 16] },
        { indexesT: [-16, 10], mode: 'ecart', expected: [0, 3] },
        { indexesT: [-16, 10], mode: 'complet', expected: [-2, 1] },
    ] as const)(
        '$indexesT in $mode gives $expected',
        ({ indexesT, mode, expected }) => {
            expect(matchStrokes(indexesT, mode)).toEqual(expected);
        },
    );

    it.each([
        { mode: 'ecart', expected: [0, 21] },
        { mode: 'complet', expected: [2, 23] },
    ] as const)(
        'Gab 1,0 and Manu 17,4 on BETHPAGE BLACK / Red in $mode give $expected',
        ({ mode, expected }) => {
            expect(matchStrokes([10, 174], mode, bethpageRed)).toEqual(
                expected,
            );
        },
    );

    it('uses the « Écart » mode by default', () => {
        expect(matchStrokes([10, 174])).toEqual([0, 16]);
    });
});
