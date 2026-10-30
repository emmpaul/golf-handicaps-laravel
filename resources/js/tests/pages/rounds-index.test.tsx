import { render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vite-plus/test';

import RoundsIndex from '@/pages/rounds/index';
import type { RoundListItem } from '@/pages/rounds/index';
import { ROUND } from '@/tests/reference-round';
import { expectPrefetchedOnTouchAndHover } from '@/tests/list-link-prefetch';

// Spec rounds « Liste des rounds », « Mise en page de la page Rounds » and
// app-shell « Rangée du haut des pages ». Head needs the head manager of
// createInertiaApp and renders nothing here; usePage is simulated for the top
// row.
// Mutations:
// - « OCT. » with a point (Intl month abbreviation instead of the design's
//   list) → the line test and the twelve months test go red;
// - the gross listed by rank (sorted by gross) instead of the order of the
//   card → the order of the card test reads « Gab 72 » first and goes red;
// - « · » dropped before the tees → the line test goes red;
// - the winners in `text-ink-2` instead of `text-accent` → the winners test
//   goes red;
// - the counter written « 8 rounds » without the capitals of the design →
//   the counter test goes red;
// - « Aucun round enregistré. » kept → the empty test goes red;
// - the swatch of each player at the slot + 1 → the line test reads
//   `bg-player-2` for Gab and goes red.

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ url: '/rounds', props: {} }),
}));

const reference: RoundListItem = {
    id: ROUND,
    playedOn: '2026-10-17',
    courseName: 'BETHPAGE BLACK',
    tees: 'Red',
    players: [
        { name: 'Gab', gross: 72, colorSlot: 0 },
        { name: 'Manu', gross: 88, colorSlot: 1 },
    ],
    netWinners: ['Gab'],
};

const roundOn = (playedOn: string, index: number): RoundListItem => ({
    ...reference,
    id: `0190a6b2-7c3e-7000-8000-0000000001${String(index).padStart(2, '0')}`,
    playedOn,
});

/** The lines of the list, in the order shown. */
const lines = () =>
    within(screen.getByRole('list', { name: 'Rounds' })).getAllByRole(
        'listitem',
    );

describe('Rounds page', () => {
    it('shows « 17 » over « OCT », the course « · Red », the gross and the winner under « 1ER NET »', () => {
        render(<RoundsIndex rounds={[reference]} />);

        const [line] = lines();
        const link = within(line).getByRole('link');

        expect(link.getAttribute('href')).toBe(`/rounds/${ROUND}`);
        expect(within(link).getByText('17').textContent).toBe('17');
        expect(within(link).getByText('OCT').textContent).toBe('OCT');
        expect(within(link).getByText('BETHPAGE BLACK')).toBeInTheDocument();
        expect(within(link).getByText('· Red').textContent).toBe('· Red');
        expect(
            within(link)
                .getAllByTestId('round-gross')
                .map((gross) => gross.textContent),
        ).toEqual(['Gab 72', 'Manu 88']);
        expect(
            within(link)
                .getAllByTestId('round-gross')
                .map(
                    (gross) =>
                        gross.querySelector('[aria-hidden="true"]')?.className,
                )
                .map((className) =>
                    className?.split(' ').find((c) => c.startsWith('bg-')),
                ),
        ).toEqual(['bg-player-1', 'bg-player-2']);
        expect(within(link).getByText('1ER NET').textContent).toBe('1ER NET');
        expect(within(link).getByTestId('round-winner').textContent).toBe(
            'Gab',
        );
    });

    it('reads the full date « 17 octobre 2026 » in a time of datetime 2026-10-17', () => {
        render(<RoundsIndex rounds={[reference]} />);

        const date = within(lines()[0]).getByText('17 octobre 2026');

        expect(date.closest('time')?.getAttribute('datetime')).toBe(
            '2026-10-17',
        );
    });

    it('abbreviates the twelve months as the design does', () => {
        const months = Array.from({ length: 12 }, (_, month) =>
            roundOn(`2026-${String(12 - month).padStart(2, '0')}-05`, month),
        );

        render(<RoundsIndex rounds={months} />);

        expect(
            lines()
                .map(
                    (line) =>
                        line.querySelector('[data-slot="round-month"]')
                            ?.textContent,
                )
                .reverse(),
        ).toEqual([
            'JANV',
            'FÉVR',
            'MARS',
            'AVR',
            'MAI',
            'JUIN',
            'JUIL',
            'AOÛT',
            'SEPT',
            'OCT',
            'NOV',
            'DÉC',
        ]);
    });

    it('keeps the gross in the order of the card', () => {
        render(
            <RoundsIndex
                rounds={[
                    {
                        ...reference,
                        players: [
                            { name: 'Manu', gross: 88, colorSlot: 1 },
                            { name: 'Gab', gross: 72, colorSlot: 0 },
                        ],
                    },
                ]}
            />,
        );

        expect(
            screen
                .getAllByTestId('round-gross')
                .map((gross) => gross.textContent),
        ).toEqual(['Manu 88', 'Gab 72']);
    });

    it('names tied winners « Gab, Manu » in amber, and « — » when nobody had an index', () => {
        render(
            <RoundsIndex
                rounds={[
                    { ...reference, netWinners: ['Gab', 'Manu'] },
                    { ...roundOn('2026-10-10', 1), netWinners: [] },
                ]}
            />,
        );

        const winners = screen.getAllByTestId('round-winner');

        expect(winners.map((winner) => winner.textContent)).toEqual([
            'Gab, Manu',
            '—',
        ]);
        expect(winners[0]).toHaveClass('text-accent');
    });

    it('keeps the order received from the server', () => {
        render(<RoundsIndex rounds={[reference, roundOn('2026-10-10', 1)]} />);

        expect(
            lines().map((line) =>
                line.querySelector('time')?.getAttribute('datetime'),
            ),
        ).toEqual(['2026-10-17', '2026-10-10']);
    });

    it('counts « 8 ROUNDS » in the top row with eight rounds', () => {
        render(
            <RoundsIndex
                rounds={Array.from({ length: 8 }, (_, day) =>
                    roundOn(
                        `2026-10-${String(17 - day).padStart(2, '0')}`,
                        day,
                    ),
                )}
            />,
        );

        const topRow = document.querySelector(
            '[data-slot="page-top-row"]',
        ) as HTMLElement;

        expect(within(topRow).getByText('8 ROUNDS').textContent).toBe(
            '8 ROUNDS',
        );
        expect(
            within(topRow)
                .getByRole('link', { name: 'Golf' })
                .getAttribute('href'),
        ).toBe('/');
    });

    it('titles the page « Rounds » and links « Parcours » to /courses', () => {
        render(<RoundsIndex rounds={[reference]} />);

        expect(screen.getByRole('heading', { level: 1 }).textContent).toBe(
            'Rounds',
        );
        expect(
            screen.getByRole('link', { name: 'Parcours' }).getAttribute('href'),
        ).toBe('/courses');
    });

    it('shows « Aucun round. Importez une carte pour commencer. » and « Saisie manuelle » without rounds', () => {
        render(<RoundsIndex rounds={[]} />);

        expect(
            screen.getByText('Aucun round. Importez une carte pour commencer.')
                .textContent,
        ).toBe('Aucun round. Importez une carte pour commencer.');
        expect(
            screen
                .getByRole('link', { name: 'Saisie manuelle' })
                .getAttribute('href'),
        ).toBe('/rounds/new');
        expect(screen.queryByRole('list', { name: 'Rounds' })).toBeNull();
    });
});

// Spec app-shell « Liens de liste préchargés », scenario « Round ouvert depuis
// la liste » (design D5): a round of /rounds prefetches its page on touch,
// before the release, and on hover.
// Mutations: the `listLinkPrefetch` props dropped from the link → no call on
// touch nor on hover, red; `onTouchStart` dropped → no call on touch, red.
describe('Rounds list link prefetch', () => {
    it('prefetches the round on touch and on hover', async () => {
        render(<RoundsIndex rounds={[reference]} />);

        await expectPrefetchedOnTouchAndHover(
            within(lines()[0]).getByRole('link'),
            '/rounds/0190a6b2-7c3e-7000-8000-0000000000aa',
        );
    });
});
