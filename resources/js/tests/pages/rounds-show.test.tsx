import { router } from '@inertiajs/react';
import type { MockInstance } from 'vite-plus/test';
import { act, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';

import RoundsShow from '@/pages/rounds/show';
import {
    GAB,
    GAB_STROKES,
    HOLES,
    MANU,
    MANU_STROKES,
    ROUND,
} from '@/tests/reference-round';

// Spec rounds « En-tête d'un round », « Classement net d'un round », « Rating
// affichée sur le round », « Carte de score d'un round », « Photo d'un round », « Suppression d'un round » and
// loading-feedback « Attentes des joueurs et des rounds ». The router's delete
// verb is stubbed (jsdom cannot run a visit); the visit callbacks it receives
// drive the dialog's own state. Head renders nothing outside createInertiaApp;
// the orb paints on a canvas jsdom cannot draw; jsdom has no ResizeObserver.
// Mutations: « · » put back between tees, date and par → the header test goes
// red; OUT summed over holes 1 to 8 (withTotals) → the card test reads 33 and
// goes red; the photo shown without `hasPhoto` → the photo test goes red; the
// pending label without « … » → the delete test goes red; « Annuler » wired to
// the delete visit → the cancel test goes red; « · » put between the rating
// and the slope → the rating test goes red; the par of the round replaced by
// 71 in « Sans rating » → the unrated test goes red; ComputationHelp removed
// from the page → the explanation test goes red.

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    Head: () => null,
}));

vi.mock('thinking-orbs', () => ({
    ThinkingOrb: () => <canvas data-testid="orb" />,
}));

const round = {
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
            gross: 72,
            indexBeforeT: 10,
            playingHandicap: 2,
            net: 70,
            rank: 2,
        },
        {
            playerId: MANU,
            name: 'Manu',
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
            { playerId: GAB, name: 'Gab', strokes: GAB_STROKES },
            { playerId: MANU, name: 'Manu', strokes: MANU_STROKES },
        ],
    },
};

type VisitOptions = { onStart?: (visit: unknown) => void };

let remove: MockInstance<typeof router.delete>;

beforeEach(() => {
    // jsdom has no layout: the scroll frame of the wide card observes nothing.
    vi.stubGlobal(
        'ResizeObserver',
        class {
            observe() {}

            disconnect() {}
        },
    );
    remove = vi.spyOn(router, 'delete').mockImplementation(() => {});
});

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

/** The cell of the wide card at the row `row` and the column headed `column`. */
function cardCell(row: string, column: string): HTMLElement {
    const table = screen.getByRole('table', { name: 'Carte de score' });
    const headers = within(table)
        .getAllByRole('columnheader')
        .map((header) => header.textContent);
    const line = within(table)
        .getByRole('rowheader', { name: row })
        .closest('tr') as HTMLElement;

    return line.children[headers.indexOf(column)] as HTMLElement;
}

describe('Round page', () => {
    it('shows « Tees Red », « 17 octobre 2026 » and « Par 71 » apart, without « · »', () => {
        render(<RoundsShow round={round} />);

        expect(screen.getByRole('heading', { level: 1 }).textContent).toBe(
            'BETHPAGE BLACK',
        );

        const summary = screen.getByTestId('round-summary');

        expect([...summary.children].map((part) => part.textContent)).toEqual([
            'Tees Red',
            '17 octobre 2026',
            'Par 71',
        ]);
        expect(summary.textContent).not.toContain('·');
    });

    it('shows the net leaderboard in the order of the card, instead of the gross', () => {
        render(<RoundsShow round={round} />);

        const leaderboard = screen.getByRole('table', {
            name: 'Classement net',
        });

        expect(
            within(leaderboard)
                .getAllByRole('row')
                .slice(1)
                .map((row) =>
                    within(row)
                        .getAllByRole('cell')
                        .map((cell) => cell.textContent),
                ),
        ).toEqual([
            ['Gab', '72', '2', '70', '2'],
            ['Manu', '88', '23', '65', '1'],
        ]);
        expect(screen.queryByRole('table', { name: 'Bruts' })).toBeNull();
    });

    it('shows « Rating 71,4 » and « Slope 144 » apart, without « · », in a link to /courses', () => {
        render(<RoundsShow round={round} />);

        const rating = screen.getByTestId('round-rating');

        expect(rating.tagName).toBe('A');
        expect(rating.getAttribute('href')).toBe('/courses');
        expect([...rating.children].map((part) => part.textContent)).toEqual([
            'Rating 71,4',
            'Slope 144',
        ]);
        expect(rating.textContent).not.toContain('·');
    });

    it('shows « Sans rating (par 72, slope 113) » in a link to /courses without rating', () => {
        render(
            <RoundsShow
                round={{
                    ...round,
                    par: 72,
                    rating: { rated: false, ratingT: 720, slope: 113 },
                }}
            />,
        );

        const rating = screen.getByTestId('round-rating');

        expect(rating.textContent).toBe('Sans rating (par 72, slope 113)');
        expect(rating.getAttribute('href')).toBe('/courses');
    });

    it('offers « Comment l’index est calculé », closed', () => {
        render(<RoundsShow round={round} />);

        const title = screen.getByText('Comment l’index est calculé');

        expect(title.tagName).toBe('SUMMARY');
        expect((title.closest('details') as HTMLDetailsElement).open).toBe(
            false,
        );
    });

    it('shows the card with the holes, the totals and the score marks', () => {
        render(<RoundsShow round={round} />);

        expect(cardCell('Gab', '1').textContent).toBe('5, bogey');
        expect(cardCell('Gab', 'OUT').textContent).toBe('38');
        expect(cardCell('Gab', 'IN').textContent).toBe('34');
        expect(cardCell('Gab', 'TOT').textContent).toBe('72');
        expect(cardCell('Manu', 'TOT').textContent).toBe('88');
        expect(cardCell('Par', 'TOT').textContent).toBe('71');
        expect(
            cardCell('Gab', '7')
                .querySelector('[data-mark]')
                ?.getAttribute('data-mark'),
        ).toBe('birdie');
        expect(
            cardCell('Gab', '1')
                .querySelector('[data-mark]')
                ?.getAttribute('data-mark'),
        ).toBe('bogey');
    });

    it('shows the card in two stacked blocks for the phone', () => {
        render(<RoundsShow round={round} />);

        const front = screen.getByRole('table', {
            name: 'Carte de score, aller',
        });
        const back = screen.getByRole('table', {
            name: 'Carte de score, retour',
        });

        expect(
            within(front)
                .getAllByRole('columnheader')
                .map((header) => header.textContent),
        ).toEqual(['Trou', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'OUT']);
        expect(
            within(back)
                .getAllByRole('columnheader')
                .map((header) => header.textContent),
        ).toEqual([
            'Trou',
            '10',
            '11',
            '12',
            '13',
            '14',
            '15',
            '16',
            '17',
            '18',
            'IN',
            'TOT',
        ]);
    });

    it('shows no « Carte du round » image without a photo', () => {
        render(<RoundsShow round={round} />);

        expect(
            screen.queryByRole('img', { name: 'Carte du round' }),
        ).toBeNull();
    });

    it('shows the photo « Carte du round », linked to itself, with a photo', () => {
        render(<RoundsShow round={{ ...round, hasPhoto: true }} />);

        const photo = screen.getByRole('img', { name: 'Carte du round' });

        expect(photo.getAttribute('src')).toBe(`/rounds/${ROUND}/photo`);
        expect(photo.closest('a')?.getAttribute('href')).toBe(
            `/rounds/${ROUND}/photo`,
        );
    });

    it('links « Modifier » to the editor of the round', () => {
        render(<RoundsShow round={round} />);

        expect(
            screen.getByRole('link', { name: 'Modifier' }).getAttribute('href'),
        ).toBe(`/rounds/${ROUND}/edit`);
    });

    it('asks for a confirmation, then shows « Suppression du round… » while deleting', async () => {
        remove.mockImplementation((_url, options) => {
            (options as VisitOptions).onStart?.({});
        });

        render(<RoundsShow round={round} />);

        await userEvent.click(
            screen.getByRole('button', { name: 'Supprimer' }),
        );

        const dialog = screen.getByRole('alertdialog');

        expect(
            within(dialog).getByRole('heading', {
                name: 'Supprimer ce round ?',
            }).textContent,
        ).toBe('Supprimer ce round ?');
        expect(remove).not.toHaveBeenCalled();

        await act(async () => {
            await userEvent.click(
                within(dialog).getByRole('button', { name: 'Supprimer' }),
            );
        });

        expect(remove).toHaveBeenCalledTimes(1);
        expect(remove.mock.calls[0][0]).toBe(`/rounds/${ROUND}`);
        expect(within(dialog).getByRole('status').textContent).toBe(
            'Suppression du round…',
        );
        expect(
            within(dialog).getByRole('button', { name: 'Supprimer' }),
        ).toBeDisabled();
    });

    it('sends nothing when the deletion is cancelled', async () => {
        render(<RoundsShow round={round} />);

        await userEvent.click(
            screen.getByRole('button', { name: 'Supprimer' }),
        );
        await userEvent.click(
            within(screen.getByRole('alertdialog')).getByRole('button', {
                name: 'Annuler',
            }),
        );

        expect(remove).not.toHaveBeenCalled();
    });
});
