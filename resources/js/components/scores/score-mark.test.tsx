import { render } from '@testing-library/react';
import { describe, expect, it } from 'vite-plus/test';

import { ScoreMark } from '@/components/scores/score-mark';

// Spec visual-theme « Marques de score »: under par in a circle of the
// « sous le par » colour, over par in a square of the « au-dessus du par »
// colour, par unmarked; each mark has its spoken text.
// Mutation: `strokes <= par` for the birdie → the par case is marked as a
// birdie and goes red.

function renderMark(strokes: number, par: number): HTMLElement {
    const { container } = render(<ScoreMark strokes={strokes} par={par} />);

    return container.firstElementChild as HTMLElement;
}

function classesOf(element: Element): string[] {
    return element.className.split(/\s+/);
}

describe('ScoreMark', () => {
    it('circles 3 on a par 4 in the under-par colour, spoken « 3, birdie »', () => {
        const mark = renderMark(3, 4);

        expect(mark).toHaveAttribute('data-mark', 'birdie');
        expect(classesOf(mark)).toContain('rounded-full');
        expect(classesOf(mark)).toContain('border-birdie');
        expect(mark.textContent).toBe('3, birdie');
    });

    it('squares 5 on a par 4 in the over-par colour, spoken « 5, bogey »', () => {
        const mark = renderMark(5, 4);

        expect(mark).toHaveAttribute('data-mark', 'bogey');
        expect(classesOf(mark)).not.toContain('rounded-full');
        expect(classesOf(mark)).toContain('border-bogey');
        expect(mark.textContent).toBe('5, bogey');
    });

    it('leaves 4 on a par 4 unmarked, spoken « 4 »', () => {
        const mark = renderMark(4, 4);

        expect(mark).not.toHaveAttribute('data-mark');
        expect(classesOf(mark)).not.toContain('rounded-full');
        expect(classesOf(mark)).not.toContain('border-birdie');
        expect(classesOf(mark)).not.toContain('border-bogey');
        expect(mark.textContent).toBe('4');
    });

    it('hides the mark name from sight, not from screen readers', () => {
        const mark = renderMark(3, 4);

        expect(mark.querySelector('.sr-only')?.textContent).toBe(', birdie');
    });
});
