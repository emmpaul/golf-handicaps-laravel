import { screen } from '@testing-library/react';

// A role query computes, for every textbox of the page, whether it is hidden
// (getComputedStyle up its ancestors) and its accessible name: about 6 ms on
// the 80 cells of the editor, against 0.01 ms for the hole lookup itself.
// Looking up the 18 holes of a row one by one runs that scan 18 times; this
// helper runs it once and returns the cells in the order of the grid, hole 1
// first. The tests that use it lean on that order: the cells reversed, the
// pars read and the strokes typed land on the wrong holes and they go red.

/** The 18 hole cells of a row (« Par », « Ligne 1 »…), hole 1 first. */
export function holeCells(row: string): HTMLInputElement[] {
    return screen.getAllByRole('textbox', {
        name: new RegExp(`^${row} trou \\d+$`),
    }) as HTMLInputElement[];
}
