import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vite-plus/test';

import { StrokesGrid } from '@/components/strokes/strokes-grid';

// Spec match-strokes « Grille des coups par trou » and « Handicap de jeu
// négatif », on BETHPAGE BLACK / Red (index 8, 16, 18, 2, 4, 10, 6, 14, 12, 9,
// 11, 7, 3, 17, 1, 5, 13, 15). The strokes per hole are the literal lists of
// the Next.js tests.
// Mutations: a hole without stroke showing « 0 » → the 16 strokes test goes
// red; « 1 coups » for one stroke → the 16 strokes test goes red; a given-back
// stroke written « -1 » with a hyphen-minus or named « -1 coup » → the −2
// strokes test goes red; grid-cols-6 instead of grid-cols-9 → the rows test
// goes red.

const SIXTEEN = [1, 1, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 1, 1, 1, 1];
const TWENTY = [1, 1, 1, 2, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 2, 1, 1, 1];
const GIVE_TWO = [0, 0, -1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, -1, 0, 0, 0, 0];

/** The visible count of each hole, holes 1 to 18. */
function counts(): string[] {
    return screen
        .getAllByRole('img')
        .map((cell) => cell.lastElementChild?.textContent ?? '');
}

describe('StrokesGrid', () => {
    it('leaves holes 3 and 14 empty for 16 strokes, « 1 » on the 16 others', () => {
        render(<StrokesGrid perHole={SIXTEEN} />);

        expect(counts()).toEqual([
            '1',
            '1',
            '',
            '1',
            '1',
            '1',
            '1',
            '1',
            '1',
            '1',
            '1',
            '1',
            '1',
            '',
            '1',
            '1',
            '1',
            '1',
        ]);
        expect(
            screen.getByRole('img', { name: 'Trou 3 : aucun coup' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('img', { name: 'Trou 14 : aucun coup' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('img', { name: 'Trou 15 : 1 coup' }),
        ).toBeInTheDocument();
    });

    it('shows « 2 » on holes 15 and 4 for 20 strokes', () => {
        render(<StrokesGrid perHole={TWENTY} />);

        expect(counts()).toEqual([
            '1',
            '1',
            '1',
            '2',
            '1',
            '1',
            '1',
            '1',
            '1',
            '1',
            '1',
            '1',
            '1',
            '1',
            '2',
            '1',
            '1',
            '1',
        ]);
        expect(
            screen.getByRole('img', { name: 'Trou 15 : 2 coups' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('img', { name: 'Trou 4 : 2 coups' }),
        ).toBeInTheDocument();
    });

    it('shows « −1 » on holes 3 and 14 for −2 strokes, named « rend 1 coup »', () => {
        render(<StrokesGrid perHole={GIVE_TWO} />);

        const third = screen.getByRole('img', { name: 'Trou 3 : rend 1 coup' });
        const fourteenth = screen.getByRole('img', {
            name: 'Trou 14 : rend 1 coup',
        });

        expect(third.lastElementChild?.textContent).toBe('−1');
        expect(third.lastElementChild?.textContent?.charCodeAt(0)).toBe(0x2212);
        expect(fourteenth.lastElementChild?.textContent).toBe('−1');
        expect(counts().filter((count) => count !== '')).toHaveLength(2);
    });

    it('lays the 18 holes in two rows of 9', () => {
        render(<StrokesGrid perHole={SIXTEEN} />);

        const grid = screen.getByTestId('strokes-holes');

        expect(grid.classList.contains('grid-cols-9')).toBe(true);
        expect(
            screen
                .getAllByRole('img')
                .map((cell) => cell.firstElementChild?.textContent),
        ).toEqual([
            '1',
            '2',
            '3',
            '4',
            '5',
            '6',
            '7',
            '8',
            '9',
            '10',
            '11',
            '12',
            '13',
            '14',
            '15',
            '16',
            '17',
            '18',
        ]);
    });
});
