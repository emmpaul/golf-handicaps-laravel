import { screen, within } from '@testing-library/react';

// The round page and the virtual match page show the same scorecard
// (RoundScorecard). Their tests read a row by its header: the texts of the
// row, header first, in the order of the columns. A score in the wrong
// column, a sum on the wrong row or a header renamed turns them red.

/** The texts of the row headed `header` of the scorecard, header first. */
export function cardRow(header: string): string[] {
    const table = screen.getByRole('table', { name: 'Carte de score' });
    const row = within(table)
        .getByRole('rowheader', { name: header })
        .closest('tr') as HTMLElement;

    return [...row.children].map((cell) => cell.textContent ?? '');
}
