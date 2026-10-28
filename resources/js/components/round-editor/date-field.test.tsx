import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { describe, expect, it } from 'vite-plus/test';

import { DateField } from '@/components/round-editor/date-field';

// Spec rounds « Choix de la date au calendrier » and « Calendrier utilisable
// au clavier et au doigt », on the real calendar (react-day-picker in a
// base-ui popover). The harness keeps the ISO value the editor would keep and
// labels the field « Date » as the editor does.
// Mutations:
// - Escape that keeps the day the arrows moved to (`onOpenChange` choosing the
//   day of `onDayFocus` on close) → the Escape test reads « 15 octobre 2026 »;
// - `weekStartsOn` forced to Sunday → the first column is « dimanche »;
// - `captionLayout="label"` → no month nor year list, the month and year test
//   goes red.

function Harness() {
    const [value, setValue] = useState<string | null>('2026-10-17');

    return (
        <>
            <label htmlFor="playedOn">Date</label>
            <DateField
                id="playedOn"
                value={value}
                invalid={false}
                onChange={setValue}
            />
            <output data-testid="iso">{value}</output>
        </>
    );
}

const dateField = () => screen.getByRole('button', { name: 'Date' });
const iso = () => screen.getByTestId('iso').textContent;

async function openCalendar() {
    await userEvent.click(dateField());

    return screen.findByRole('grid');
}

describe('DateField calendar', () => {
    it('opens on October 2026 with the 17th selected, Monday first', async () => {
        render(<Harness />);

        expect(dateField().textContent).toBe('17 octobre 2026');

        const grid = await openCalendar();

        expect(grid.getAttribute('aria-label')).toBe('octobre 2026');
        expect(
            grid
                .querySelector('[aria-selected="true"]')
                ?.getAttribute('data-day'),
        ).toBe('2026-10-17');
        expect(
            [...grid.querySelectorAll('thead th')].map((day) =>
                day.getAttribute('aria-label'),
            ),
        ).toEqual([
            'lundi',
            'mardi',
            'mercredi',
            'jeudi',
            'vendredi',
            'samedi',
            'dimanche',
        ]);
        expect(
            within(grid).getAllByRole('gridcell')[0].getAttribute('data-day'),
        ).toBe('2026-09-28');
    });

    it('picks the 15th with two Left arrows and Enter, then gives the focus back to « Date »', async () => {
        render(<Harness />);

        dateField().focus();
        await userEvent.keyboard('{Enter}');
        await screen.findByRole('grid');

        await waitFor(() =>
            expect(document.activeElement?.getAttribute('aria-label')).toBe(
                'samedi 17 octobre 2026, sélectionné',
            ),
        );

        await userEvent.keyboard('{ArrowLeft}{ArrowLeft}{Enter}');
        await waitFor(() => expect(screen.queryByRole('grid')).toBeNull());

        expect(dateField().textContent).toBe('15 octobre 2026');
        expect(iso()).toBe('2026-10-15');
        await waitFor(() => expect(document.activeElement).toBe(dateField()));
    });

    it('closes on Escape without changing the date, even after moving with the arrows', async () => {
        render(<Harness />);

        await openCalendar();

        await userEvent.keyboard('{ArrowLeft}{ArrowLeft}{Escape}');
        await waitFor(() => expect(screen.queryByRole('grid')).toBeNull());

        expect(dateField().textContent).toBe('17 octobre 2026');
        expect(iso()).toBe('2026-10-17');
        await waitFor(() => expect(document.activeElement).toBe(dateField()));
    });

    it('picks the 13th with a click', async () => {
        render(<Harness />);

        const grid = await openCalendar();

        await userEvent.click(
            within(grid).getByRole('button', { name: 'mardi 13 octobre 2026' }),
        );

        expect(dateField().textContent).toBe('13 octobre 2026');
        expect(iso()).toBe('2026-10-13');
    });

    it('chooses March, then 2025, then the 4th without paging month by month', async () => {
        render(<Harness />);

        await openCalendar();

        await userEvent.selectOptions(
            screen.getByRole('combobox', { name: 'Choisir le mois' }),
            'mars',
        );
        await userEvent.selectOptions(
            screen.getByRole('combobox', { name: "Choisir l'année" }),
            '2025',
        );
        await userEvent.click(
            within(screen.getByRole('grid', { name: 'mars 2025' })).getByRole(
                'button',
                { name: 'mardi 4 mars 2025' },
            ),
        );

        expect(dateField().textContent).toBe('4 mars 2025');
        expect(iso()).toBe('2025-03-04');
    });
});
