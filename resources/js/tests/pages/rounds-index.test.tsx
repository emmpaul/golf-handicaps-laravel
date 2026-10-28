import { render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vite-plus/test';

import RoundsIndex from '@/pages/rounds/index';
import { ROUND } from '@/tests/reference-round';

// Spec rounds « Liste des rounds ». Head needs the head manager of
// createInertiaApp and renders nothing here.
// Mutations: « · » put back between the course and the tees → the line test
// goes red; « Aucun round enregistré. » dropped or the « Saisie manuelle » link
// pointed elsewhere → the empty test goes red; the gross rendered as
// « Gab : 72 » → the line test goes red; the net winners joined by « / » →
// the winners test goes red; « Parcours » pointed elsewhere → the courses
// link test goes red.

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    Head: () => null,
}));

const reference = {
    id: ROUND,
    playedOn: '2026-10-17',
    courseName: 'BETHPAGE BLACK',
    tees: 'Red',
    players: [
        { name: 'Gab', gross: 72 },
        { name: 'Manu', gross: 88 },
    ],
    netWinners: ['Gab', 'Manu'],
};

/** The « Gagnant net » cell of each line, in the order shown. */
function winners(): string[] {
    return screen
        .getAllByTestId('round-winner')
        .map((winner) => winner.textContent ?? '');
}

describe('Rounds page', () => {
    it('shows the date, course, tees and gross of a round, without « · »', () => {
        render(<RoundsIndex rounds={[reference]} />);

        const list = screen.getByRole('list', { name: 'Rounds' });
        const [line] = within(list).getAllByRole('listitem');
        const link = within(line).getByRole('link');

        expect(link.getAttribute('href')).toBe(`/rounds/${ROUND}`);
        expect(within(link).getByText('17 octobre 2026').tagName).toBe('TIME');
        expect(within(link).getByText('BETHPAGE BLACK').textContent).toBe(
            'BETHPAGE BLACK',
        );
        expect(within(link).getByText('Red').textContent).toBe('Red');
        expect(
            within(link)
                .getAllByTestId('round-gross')
                .map((gross) => gross.textContent),
        ).toEqual(['Gab 72', 'Manu 88']);
        expect(line.textContent).not.toContain('·');
        expect(screen.queryByText('Aucun round enregistré.')).toBeNull();
    });

    it('shows « Gab, Manu » as tied net winners, and « — » when nobody had an index', () => {
        render(
            <RoundsIndex
                rounds={[
                    reference,
                    {
                        ...reference,
                        id: '0190a6b2-7c3e-7000-8000-0000000000ab',
                        playedOn: '2026-10-10',
                        netWinners: [],
                    },
                ]}
            />,
        );

        expect(winners()).toEqual([
            'Gagnant net : Gab, Manu',
            'Gagnant net : —',
        ]);
    });

    it('heads the register with « Gagnant net »', () => {
        render(<RoundsIndex rounds={[reference]} />);

        expect(screen.getByText('Gagnant net').textContent).toBe('Gagnant net');
    });

    it('links « Parcours » to /courses, beside « Saisie manuelle »', () => {
        render(<RoundsIndex rounds={[]} />);

        expect(
            screen.getByRole('link', { name: 'Parcours' }).getAttribute('href'),
        ).toBe('/courses');
    });

    it('keeps the order received from the server', () => {
        render(
            <RoundsIndex
                rounds={[
                    reference,
                    {
                        ...reference,
                        id: '0190a6b2-7c3e-7000-8000-0000000000ab',
                        playedOn: '2026-10-10',
                    },
                ]}
            />,
        );

        const list = screen.getByRole('list', { name: 'Rounds' });

        expect(
            [...list.querySelectorAll('time')].map((time) =>
                time.getAttribute('datetime'),
            ),
        ).toEqual(['2026-10-17', '2026-10-10']);
    });

    it('shows « Aucun round enregistré. » and the « Saisie manuelle » link without rounds', () => {
        render(<RoundsIndex rounds={[]} />);

        expect(screen.getByText('Aucun round enregistré.').textContent).toBe(
            'Aucun round enregistré.',
        );
        expect(
            screen
                .getByRole('link', { name: 'Saisie manuelle' })
                .getAttribute('href'),
        ).toBe('/rounds/new');
        expect(screen.queryAllByRole('listitem')).toEqual([]);
    });
});
