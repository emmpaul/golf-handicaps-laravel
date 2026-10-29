import { render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vite-plus/test';

import RoundsShow from '@/pages/rounds/show';
import type { RoundDetail } from '@/pages/rounds/show';
import {
    GAB,
    GAB_STROKES,
    HOLES,
    MANU,
    MANU_STROKES,
    ROUND,
} from '@/tests/reference-round';

// Spec rounds « En-tête d'un round », « Rating affichée sur le round »,
// « Carte de score d'un round », « Mise en page de la page d'un round »,
// « Photo et explication sur la page d'un round » and app-shell « Rangée du
// haut des pages ». The leaderboard itself: net-leaderboard.test.tsx; the
// deletion sheet: delete-round.test.tsx. Head renders nothing outside
// createInertiaApp; usePage is simulated for the top row; the orb paints on a
// canvas jsdom cannot draw.
// Mutations:
// - the date of the meta written « 17/10/2026 » → the header test goes red;
// - the tees of the meta left in their case (« Red ») → the header test goes
//   red;
// - « SANS RATING » not linked to /courses → the unrated test goes red;
// - the rating written « 71,4 · 144 » → the rated test goes red;
// - the leaderboard kept in the order of the card → the leaderboard test
//   reads Gab first and goes red;
// - the TOTAL row dropped → the card test finds no « TOTAL · PAR 71 »;
// - ALLER summed over holes 1 to 8 → the card test reads 33 and goes red;
// - the photo put above the legend → the photo test goes red;
// - ComputationHelp removed → the explanation test goes red;
// - « Modifier » left at the foot of the page → the top row test goes red.

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ url: `/rounds/${ROUND}`, props: {} }),
}));

vi.mock('thinking-orbs', () => ({
    ThinkingOrb: () => <canvas data-testid="orb" />,
}));

const round: RoundDetail = {
    id: ROUND,
    courseName: 'BETHPAGE BLACK',
    tees: 'Red',
    playedOn: '2026-10-17',
    par: 71,
    hasPhoto: false,
    leaderboard: [
        {
            playerId: GAB,
            name: 'Gab',
            colorSlot: 0,
            gross: 72,
            indexBeforeT: 10,
            playingHandicap: 2,
            net: 70,
            rank: 2,
        },
        {
            playerId: MANU,
            name: 'Manu',
            colorSlot: 1,
            gross: 88,
            indexBeforeT: 174,
            playingHandicap: 23,
            net: 65,
            rank: 1,
        },
    ],
    rating: { rated: true, ratingT: 714, slope: 144 },
    card: {
        holes: HOLES,
        lines: [
            { playerId: GAB, name: 'Gab', colorSlot: 0, strokes: GAB_STROKES },
            {
                playerId: MANU,
                name: 'Manu',
                colorSlot: 1,
                strokes: MANU_STROKES,
            },
        ],
    },
};

const unrated: RoundDetail = {
    ...round,
    rating: { rated: false, ratingT: 710, slope: 113 },
};

/** The texts of the row headed `header` of the card, header first. */
function cardRow(header: string): string[] {
    const table = screen.getByRole('table', { name: 'Carte de score' });
    const row = within(table)
        .getByRole('rowheader', { name: header })
        .closest('tr') as HTMLElement;

    return [...row.children].map((cell) => cell.textContent ?? '');
}

const topRow = () =>
    document.querySelector('[data-slot="page-top-row"]') as HTMLElement;

describe('Round page', () => {
    it('titles the page with the course and joins the meta by « · »', () => {
        render(<RoundsShow round={unrated} />);

        expect(screen.getByRole('heading', { level: 1 }).textContent).toBe(
            'BETHPAGE BLACK',
        );
        expect(screen.getByTestId('round-meta').textContent).toBe(
            'RED · 17.10.2026 · PAR 71 · SANS RATING',
        );
    });

    it('links « SANS RATING » to /courses without rating', () => {
        render(<RoundsShow round={unrated} />);

        const rating = within(screen.getByTestId('round-meta')).getByRole(
            'link',
        );

        expect(rating.textContent).toBe('SANS RATING');
        expect(rating.getAttribute('href')).toBe('/courses');
    });

    it('ends the meta with « 71,4 / 144 », a link to /courses', () => {
        render(<RoundsShow round={round} />);

        const rating = within(screen.getByTestId('round-meta')).getByRole(
            'link',
        );

        expect(screen.getByTestId('round-meta').textContent).toBe(
            'RED · 17.10.2026 · PAR 71 · 71,4 / 144',
        );
        expect(rating.textContent).toBe('71,4 / 144');
        expect(rating.getAttribute('href')).toBe('/courses');
    });

    it('ranks the leaderboard by net under « Classement net » and « Brut − hcp »', () => {
        render(<RoundsShow round={round} />);

        const leaderboard = screen.getByRole('list', {
            name: 'Classement net',
        });

        expect(
            within(leaderboard)
                .getAllByRole('listitem')
                .map(
                    (line) =>
                        line.querySelector('[data-slot="leaderboard-name"]')
                            ?.textContent,
                ),
        ).toEqual(['Manu', 'Gab']);
        expect(screen.getByText('Brut − hcp').textContent).toBe('Brut − hcp');
    });

    it('shows a row per hole, then ALLER, RETOUR and TOTAL with the sums of each player', () => {
        render(<RoundsShow round={round} />);

        const table = screen.getByRole('table', { name: 'Carte de score' });

        expect(
            within(table)
                .getAllByRole('columnheader')
                .map((header) => header.textContent),
        ).toEqual(['Trou', 'Par · idx', 'GAB', 'MANU']);
        expect(cardRow('1')).toEqual([
            '1',
            'P4 · 8',
            '5, bogey',
            '7, double bogey ou plus',
        ]);
        expect(cardRow('ALLER · PAR 36')).toEqual([
            'ALLER · PAR 36',
            '38',
            '46',
        ]);
        expect(cardRow('RETOUR · PAR 35')).toEqual([
            'RETOUR · PAR 35',
            '34',
            '42',
        ]);
        expect(cardRow('TOTAL · PAR 71')).toEqual([
            'TOTAL · PAR 71',
            '72',
            '88',
        ]);
        expect(
            within(table)
                .getAllByRole('rowheader')
                .map((header) => header.textContent),
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
            'ALLER · PAR 36',
            '10',
            '11',
            '12',
            '13',
            '14',
            '15',
            '16',
            '17',
            '18',
            'RETOUR · PAR 35',
            'TOTAL · PAR 71',
        ]);
    });

    it('marks Gab’s 4 at hole 7 (par 5) birdie and his 5 at hole 1 bogey', () => {
        render(<RoundsShow round={round} />);

        const mark = (hole: string) =>
            within(
                screen
                    .getByRole('table', { name: 'Carte de score' })
                    .querySelector('tbody') as HTMLElement,
            )
                .getByRole('rowheader', { name: hole })
                .closest('tr')
                ?.querySelectorAll('td')[1]
                .querySelector('[data-mark]')
                ?.getAttribute('data-mark');

        expect(mark('7')).toBe('birdie');
        expect(mark('1')).toBe('bogey');
    });

    it('shows the legend under the card', () => {
        render(<RoundsShow round={round} />);

        const legend = screen.getByRole('list', { name: 'Légende' });

        expect(
            within(legend)
                .getAllByRole('listitem')
                .map((item) => item.textContent),
        ).toEqual(['Eagle', 'Birdie', 'Bogey', 'Double +']);
        expect(
            screen
                .getByRole('table', { name: 'Carte de score' })
                .compareDocumentPosition(legend) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
    });

    it('shows no « Carte du round » image without a photo', () => {
        render(<RoundsShow round={round} />);

        expect(
            screen.queryByRole('img', { name: 'Carte du round' }),
        ).toBeNull();
    });

    it('shows the photo « Carte du round » under the legend, linked to itself', () => {
        render(<RoundsShow round={{ ...round, hasPhoto: true }} />);

        const photo = screen.getByRole('img', { name: 'Carte du round' });

        expect(photo.getAttribute('src')).toBe(`/rounds/${ROUND}/photo`);
        expect(photo.closest('a')?.getAttribute('href')).toBe(
            `/rounds/${ROUND}/photo`,
        );
        expect(photo).toHaveClass('aspect-[16/10]', 'rounded-12');
        expect(
            screen
                .getByRole('list', { name: 'Légende' })
                .compareDocumentPosition(photo) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
    });

    it('offers « Comment l’index est calculé », closed, after the card', () => {
        render(<RoundsShow round={round} />);

        const title = screen.getByText('Comment l’index est calculé');

        expect(title.tagName).toBe('SUMMARY');
        expect((title.closest('details') as HTMLDetailsElement).open).toBe(
            false,
        );
    });

    it('puts the back link « Rounds », « Modifier » and « Supprimer » in the top row', () => {
        render(<RoundsShow round={round} />);

        expect(
            within(topRow())
                .getByRole('link', { name: 'Rounds' })
                .getAttribute('href'),
        ).toBe('/rounds');
        expect(
            within(topRow())
                .getByRole('link', { name: 'Modifier' })
                .getAttribute('href'),
        ).toBe(`/rounds/${ROUND}/edit`);
        expect(
            within(topRow()).getByRole('button', { name: 'Supprimer' }),
        ).toBeInTheDocument();
    });
});
