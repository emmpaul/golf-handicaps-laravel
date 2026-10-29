import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vite-plus/test';

import { StrokeHoleGrid } from '@/components/strokes/stroke-hole-grid';

// Spec match-strokes « Grille des coups par trou » and « Handicap de jeu
// négatif », on BETHPAGE BLACK / Red (index 8, 16, 18, 2, 4, 10, 6, 14, 12, 9,
// 11, 7, 3, 17, 1, 5, 13, 15). The strokes per hole are the literal lists of
// the Next.js tests.
// Spec « Bloc de résultat des coups » (visual-redesign): cells of 44 px, the
// hole number in Geist Mono 9 px grey, « 1 » in Geist Mono 600 15 px in the
// colour of the player on `surface-3`, « · » drawn as a dot of `ink-4` on a
// transparent background for a hole without stroke, hidden from screen
// readers (DO27: never a readable text in ink-4).
// Mutations: a hole without stroke showing « 0 » or nothing instead of « · »
// → the 16 strokes test goes red; « 1 coups » for one stroke → the 16 strokes
// test goes red; a given-back stroke written « -1 » with a hyphen-minus or
// named « -1 coup » → the −2 strokes test goes red; grid-cols-6 instead of
// grid-cols-9 → the rows test goes red; the count in `text-ink` instead of
// the colour of the player → the cell test goes red.

const SIXTEEN = [1, 1, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 1, 1, 1, 1];
const TWENTY = [1, 1, 1, 2, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 2, 1, 1, 1];
const GIVE_TWO = [0, 0, -1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, -1, 0, 0, 0, 0];

/** The visible count of each hole, holes 1 to 18; « · » for the drawn dot of a hole without stroke. */
function counts(): string[] {
    return screen
        .getAllByRole('img')
        .map((cell) =>
            cell.lastElementChild?.getAttribute('data-slot') === 'no-stroke'
                ? '·'
                : (cell.lastElementChild?.textContent ?? ''),
        );
}

describe('StrokeHoleGrid', () => {
    it('leaves holes 3 and 14 empty for 16 strokes, « 1 » on the 16 others', () => {
        render(<StrokeHoleGrid perHole={SIXTEEN} colorSlot={1} />);

        expect(counts()).toEqual([
            '1',
            '1',
            '·',
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
            '·',
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
        render(<StrokeHoleGrid perHole={TWENTY} colorSlot={1} />);

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
        render(<StrokeHoleGrid perHole={GIVE_TWO} colorSlot={1} />);

        const third = screen.getByRole('img', { name: 'Trou 3 : rend 1 coup' });
        const fourteenth = screen.getByRole('img', {
            name: 'Trou 14 : rend 1 coup',
        });

        expect(third.lastElementChild?.textContent).toBe('−1');
        expect(third.lastElementChild?.textContent?.charCodeAt(0)).toBe(0x2212);
        expect(fourteenth.lastElementChild?.textContent).toBe('−1');
        expect(counts().filter((count) => count !== '·')).toHaveLength(2);
    });

    it('draws a cell of 44 px: a stroke in the colour of the player on surface-3, « · » in ink-4 hidden from screen readers', () => {
        render(<StrokeHoleGrid perHole={SIXTEEN} colorSlot={1} />);

        const first = screen.getByRole('img', { name: 'Trou 1 : 1 coup' });
        const third = screen.getByRole('img', { name: 'Trou 3 : aucun coup' });

        expect(first.className).toContain('h-11');
        expect(first.className).toContain('bg-surface-3');
        expect(first.firstElementChild?.className).toContain('text-caption-9');
        expect(first.lastElementChild?.className).toContain('text-player-2');
        expect(first.lastElementChild?.className).toContain('font-semibold');
        expect(third.className).toContain('bg-transparent');
        expect(third.lastElementChild?.textContent).toBe('');
        expect(third.lastElementChild?.firstElementChild?.className).toContain(
            'bg-ink-4',
        );
        expect(third.lastElementChild?.getAttribute('aria-hidden')).toBe(
            'true',
        );
    });

    it('lays the 18 holes in two rows of 9', () => {
        render(<StrokeHoleGrid perHole={SIXTEEN} colorSlot={1} />);

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
