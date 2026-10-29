import { render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vite-plus/test';

import { ScoreLegend, ScoreMark } from '@/components/scores/score-mark';

// Spec visual-theme « Marques de score » (Eagle, Birdie, Bogey, Double bogey,
// Par): eagle or better in a double circle and birdie in a circle, digit in
// `signal`; bogey in a square and double bogey or worse in a double square,
// borders in `over`; par unmarked; each mark has its spoken text. jsdom
// computes no layout: the size and colour classes are the contract.
// Mutations: eagle threshold at −1 (`strokes - par <= -1`) → 3 on a par 4 is
// drawn as an eagle and the birdie test goes red; double square from +1
// (`strokes - par >= 1`) → 5 on a par 4 is a double bogey and the bogey test
// goes red.

function renderMark(strokes: number, par: number): HTMLElement {
    const { container } = render(<ScoreMark strokes={strokes} par={par} />);

    return container.firstElementChild as HTMLElement;
}

/** The inner ring of a double mark. */
function inner(mark: HTMLElement): HTMLElement {
    return mark.querySelector('[data-slot="score-mark-inner"]')!;
}

describe('ScoreMark', () => {
    it('draws 3 on a par 5 in a double circle, digit in signal, spoken « 3, eagle »', () => {
        const mark = renderMark(3, 5);

        expect(mark).toHaveAttribute('data-mark', 'eagle');
        expect(mark).toHaveClass('size-8', 'rounded-full', 'border-signal');
        expect(inner(mark)).toHaveClass(
            'size-6',
            'rounded-full',
            'border-signal',
            'text-signal',
        );
        expect(mark.textContent).toBe('3, eagle');
    });

    it('draws 2 on a par 5 as an eagle too, spoken « 2, eagle »', () => {
        const mark = renderMark(2, 5);

        expect(mark).toHaveAttribute('data-mark', 'eagle');
        expect(mark.textContent).toBe('2, eagle');
    });

    it('circles 3 on a par 4 in a 30 px circle, digit in signal, spoken « 3, birdie »', () => {
        const mark = renderMark(3, 4);

        expect(mark).toHaveAttribute('data-mark', 'birdie');
        expect(mark).toHaveClass(
            'size-[30px]',
            'rounded-full',
            'border-signal',
            'text-signal',
        );
        expect(inner(mark)).toBeNull();
        expect(mark.textContent).toBe('3, birdie');
    });

    it('leaves 4 on a par 4 unmarked, spoken « 4 »', () => {
        const mark = renderMark(4, 4);

        expect(mark).not.toHaveAttribute('data-mark');
        expect(mark).not.toHaveClass('border-signal');
        expect(mark).not.toHaveClass('border-over');
        expect(mark.textContent).toBe('4');
    });

    it('squares 5 on a par 4 in a 28 px square, spoken « 5, bogey »', () => {
        const mark = renderMark(5, 4);

        expect(mark).toHaveAttribute('data-mark', 'bogey');
        expect(mark).toHaveClass('size-7', 'border-over');
        expect(mark).not.toHaveClass('rounded-full');
        expect(inner(mark)).toBeNull();
        expect(mark.textContent).toBe('5, bogey');
    });

    it.each([
        [7, '7, double bogey ou plus'],
        [9, '9, double bogey ou plus'],
    ])(
        'draws %i on a par 4 in a double square, weight 600, spoken « %s »',
        (strokes, spoken) => {
            const mark = renderMark(strokes, 4);

            expect(mark).toHaveAttribute('data-mark', 'double');
            expect(mark).toHaveClass('size-8', 'border-over');
            expect(inner(mark)).toHaveClass(
                'size-6',
                'border-over',
                'font-semibold',
            );
            expect(mark.textContent).toBe(spoken);
        },
    );

    it('hides the mark name from sight, not from screen readers', () => {
        const mark = renderMark(3, 4);

        expect(mark.querySelector('.sr-only')?.textContent).toBe(', birdie');
    });
});

describe('ScoreLegend', () => {
    it('names the four marks in order, drawings hidden from screen readers', () => {
        render(<ScoreLegend />);

        const legend = screen.getByRole('list', { name: 'Légende' });
        const items = within(legend).getAllByRole('listitem');

        expect(items.map((item) => item.textContent)).toEqual([
            'Eagle',
            'Birdie',
            'Bogey',
            'Double +',
        ]);
        items.forEach((item) =>
            expect(item.firstElementChild).toHaveAttribute(
                'aria-hidden',
                'true',
            ),
        );
    });
});
