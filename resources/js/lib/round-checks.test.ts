import { describe, expect, it } from 'vite-plus/test';
import {
    checkRoundCard,
    problemCountLabel,
    type Problem,
    type RoundCard,
    withTotals,
} from '@/lib/round-checks';
import fixture from '../../../tests/fixtures/round-checks.json';

// tests/fixtures/round-checks.json is shared with tests/Unit/Rounds/RoundCardChecksTest.php:
// a gap between the TypeScript and PHP checks turns one of the two red.
//
// Mutations that turn these tests red:
// - the score bound at 14: « Score 16 hors de 1 à 14 » instead of « … 1 à 15 », and the
//   score of 15 of the « score 15 » case is refused;
// - the duplicate index not detected: the « index 1 en double » case finds no problem;
// - OUT summed over holes 1 to 8: OUT 33 instead of 38;
// - an empty hole counted as 0: OUT 33 and TOT 67 instead of null.
const cases = fixture.cases as {
    name: string;
    card: RoundCard;
    problems: Problem[];
}[];

describe('checkRoundCard', () => {
    it('reads the cases of the shared table', () => {
        expect(cases.map(({ name }) => name)).toEqual([
            'carte de référence',
            'distances toutes vides',
            'index 1 en double',
            'par 6',
            'score 16',
            'score 15',
            'distance 1000',
            'case vide',
            'ligne sans joueur',
            'joueur en double',
            '0 ligne',
            '5 lignes',
            'parcours vide',
            'tees absents',
            'date vide',
        ]);
    });

    it.each(cases)('$name', ({ card, problems }) => {
        expect(checkRoundCard(card)).toEqual(problems);
    });
});

describe('problemCountLabel', () => {
    it.each([
        [1, '1 problème à corriger'],
        [3, '3 problèmes à corriger'],
    ])('labels %i', (count, label) => {
        expect(problemCountLabel(count)).toBe(label);
    });
});

describe('withTotals', () => {
    it('sums a full front nine into OUT', () => {
        expect(
            withTotals([5, 5, 3, 5, 4, 4, 4, 3, 5, 4, 4, 4, 4, 3, 4, 5, 3, 3]),
        ).toEqual({
            holes: [5, 5, 3, 5, 4, 4, 4, 3, 5, 4, 4, 4, 4, 3, 4, 5, 3, 3],
            out: 38,
            in: 34,
            total: 72,
        });
    });

    it('leaves OUT and TOT empty when a front hole is empty', () => {
        const totals = withTotals([
            5,
            5,
            3,
            5,
            4,
            4,
            4,
            3,
            null,
            4,
            4,
            4,
            4,
            3,
            4,
            5,
            3,
            3,
        ]);

        expect([totals.out, totals.in, totals.total]).toEqual([null, 34, null]);
    });
});
