import { router } from '@inertiajs/react';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    type MockInstance,
    vi,
} from 'vite-plus/test';

import RoundsCompare from '@/pages/rounds/compare';
import type { VirtualMatchPage } from '@/pages/rounds/compare';
import { GAB, HOLES, MANU, MANU_STROKES, SEAN } from '@/tests/reference-round';
import { cardRow } from '@/tests/scorecard-rows';

// Spec virtual-match « Choix du parcours de la partie virtuelle », « Au moins
// deux joueurs », « Classement de la partie virtuelle », « Encadré du
// gagnant », « Carte combinée », « Pars et index de la carte combinée » and
// « Mise en page de la page Partie virtuelle ». The props are the literals of
// the scenario « Trois joueurs, deux jours »; the texts of the winner box come
// from winnerSummary (virtual-match.test.ts), the pills and the add list from
// virtual-match-picks.test.tsx. Head renders nothing outside createInertiaApp,
// usePage is simulated for the top row, router.get is stubbed.
// Mutations:
// - the back link sent to `/` → the top row test goes red;
// - the rating line written « 71,4 / 144 », not linked to /courses or under
//   44 px high (min-h-8) → the course test goes red;
// - the leaderboard or the card button kept with one pick → the one pick test
//   goes red;
// - the winner box below the pills → the winner test goes red;
// - the dates not passed to the leaderboard → the leaderboard test finds no
//   « 18 nov. 2026 »;
// - the card unfolded by a visit instead of a local state → the card test
//   sees router.get called;
// - the note shown without `holesDiffer` → the identical cards test goes red;
// - the picks kept on a change of course → the course change test reads
//   `picks=` and goes red;
// - the list shown without course → the empty test finds a combobox.

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ url: '/rounds/compare', props: {} }),
}));

const GAB_ROUND = '0190a6b2-7c3e-7000-8000-0000000000b1';
const MANU_SEAN_ROUND = '0190a6b2-7c3e-7000-8000-0000000000b2';

const GAB_STROKES_74 = [6, 6, 3, 5, 4, 4, 4, 3, 5, 4, 4, 4, 4, 3, 4, 5, 3, 3];
const SEAN_STROKES_84 = [6, 4, 3, 6, 5, 5, 5, 3, 5, 3, 6, 5, 7, 4, 5, 4, 3, 5];

const courses = [
    { courseName: 'BETHPAGE BLACK', tees: 'Red' },
    { courseName: 'PAYNES VALLEY', tees: 'Blue' },
];

const gab = {
    roundId: GAB_ROUND,
    playerId: GAB,
    name: 'Gab',
    colorSlot: 0,
    playedOn: '2026-11-18',
};
const manu = {
    roundId: MANU_SEAN_ROUND,
    playerId: MANU,
    name: 'Manu',
    colorSlot: 1,
    playedOn: '2026-11-19',
};
const sean = {
    roundId: MANU_SEAN_ROUND,
    playerId: SEAN,
    name: 'Sean',
    colorSlot: 2,
    playedOn: '2026-11-19',
};

const match: NonNullable<VirtualMatchPage['match']> = {
    courseName: 'BETHPAGE BLACK',
    tees: 'Red',
    rating: { rated: true, ratingT: 714, slope: 144 },
    candidates: [
        { ...manu, partners: ['Sean'], gross: 88 },
        { ...sean, partners: ['Manu'], gross: 84 },
        { ...gab, partners: [], gross: 74 },
    ],
    picks: [
        { ...gab, strokes: GAB_STROKES_74 },
        { ...manu, strokes: MANU_STROKES },
        { ...sean, strokes: SEAN_STROKES_84 },
    ],
    leaderboard: [
        {
            ...manu,
            gross: 88,
            indexBeforeT: 174,
            playingHandicap: 23,
            net: 65,
            rank: 1,
        },
        {
            ...sean,
            gross: 84,
            indexBeforeT: 125,
            playingHandicap: 16,
            net: 68,
            rank: 2,
        },
        {
            ...gab,
            gross: 74,
            indexBeforeT: 10,
            playingHandicap: 2,
            net: 72,
            rank: 3,
        },
    ],
    holes: HOLES.map(({ par, strokeIndex }) => ({ par, strokeIndex })),
    holesDiffer: false,
};

const onlyGab: NonNullable<VirtualMatchPage['match']> = {
    ...match,
    picks: [match.picks[0]],
    leaderboard: [match.leaderboard[2]],
};

const PROMPT = 'Choisissez au moins 2 joueurs.';

let get: MockInstance<typeof router.get>;

beforeEach(() => {
    get = vi.spyOn(router, 'get').mockImplementation(() => {});
});

afterEach(() => {
    vi.restoreAllMocks();
});

const topRow = () =>
    document.querySelector('[data-slot="page-top-row"]') as HTMLElement;

describe('Virtual match page', () => {
    it('puts the back link « Rounds » to /rounds in the top row, then « Partie virtuelle »', () => {
        render(<RoundsCompare courses={courses} match={match} />);

        expect(
            within(topRow())
                .getByRole('link', { name: 'Rounds' })
                .getAttribute('href'),
        ).toBe('/rounds');
        expect(screen.getByRole('heading', { level: 1 }).textContent).toBe(
            'Partie virtuelle',
        );
    });

    it('lists the known courses under « Parcours », with the rating line linked to /courses', () => {
        render(<RoundsCompare courses={courses} match={match} />);

        const list = screen.getByRole('combobox', { name: 'Parcours' });

        expect(
            within(list)
                .getAllByRole('option')
                .map((option) => option.textContent),
        ).toEqual(['BETHPAGE BLACK / Red', 'PAYNES VALLEY / Blue']);
        expect((list as HTMLSelectElement).selectedOptions[0].textContent).toBe(
            'BETHPAGE BLACK / Red',
        );

        const rating = screen.getByRole('link', {
            name: 'rating 71,4 · slope 144',
        });

        expect(rating.getAttribute('href')).toBe('/courses');
        // DESIGN.md: touch targets of 44 px at least.
        expect(rating).toHaveClass('min-h-11');
    });

    it('reads « sans rating (slope 113) » without a rating', () => {
        render(
            <RoundsCompare
                courses={courses}
                match={{
                    ...match,
                    rating: { rated: false, ratingT: null, slope: 113 },
                }}
            />,
        );

        expect(
            screen
                .getByRole('link', { name: 'sans rating (slope 113)' })
                .getAttribute('href'),
        ).toBe('/courses');
    });

    it('shows « Choisissez au moins 2 joueurs. » in a dashed frame, without box, leaderboard nor card button, with one pick', () => {
        render(<RoundsCompare courses={courses} match={onlyGab} />);

        expect(screen.getByText(PROMPT)).toHaveClass('border-dashed');
        expect(screen.queryByText('AURAIT GAGNÉ')).toBeNull();
        expect(
            screen.queryByRole('list', { name: 'Classement net' }),
        ).toBeNull();
        expect(
            screen.queryByRole('button', { name: 'Voir la carte combinée' }),
        ).toBeNull();
        expect(
            screen.getByRole('button', { name: 'Retirer Gab, 18 nov. 2026' }),
        ).toBeInTheDocument();
    });

    it('shows « AURAIT GAGNÉ », « Manu » and « 65 net · 3 coups devant Sean » above the pills', () => {
        render(<RoundsCompare courses={courses} match={match} />);

        const box = screen.getByRole('region', { name: 'AURAIT GAGNÉ' });

        expect(within(box).getByText('Manu')).toHaveClass(
            'font-mono',
            'text-display-l',
        );
        expect(
            within(box).getByText('65 net · 3 coups devant Sean'),
        ).toBeInTheDocument();
        expect(box).toHaveClass('bg-surface-1', 'rounded-14');
        expect(
            box.compareDocumentPosition(
                screen.getByRole('list', { name: 'Joueurs-rounds choisis' }),
            ) & Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
        expect(screen.queryByText(PROMPT)).toBeNull();
    });

    it('shows no winner box with one ranked player, but the leaderboard', () => {
        render(
            <RoundsCompare
                courses={courses}
                match={{
                    ...match,
                    picks: [match.picks[0], match.picks[2]],
                    leaderboard: [
                        { ...match.leaderboard[2], rank: 1 },
                        {
                            ...match.leaderboard[1],
                            gross: 95,
                            indexBeforeT: null,
                            playingHandicap: null,
                            net: null,
                            rank: null,
                        },
                    ],
                }}
            />,
        );

        expect(screen.queryByText('AURAIT GAGNÉ')).toBeNull();
        expect(
            screen.getByRole('list', { name: 'Classement net' }),
        ).toBeInTheDocument();
    });

    it('ranks Manu, Sean and Gab with « 88 − 23 » and the date of each round', () => {
        render(<RoundsCompare courses={courses} match={match} />);

        const lines = within(
            screen.getByRole('list', { name: 'Classement net' }),
        ).getAllByRole('listitem');
        const slot = (line: HTMLElement, name: string) =>
            line.querySelector(`[data-slot="leaderboard-${name}"]`)
                ?.textContent;

        expect(
            lines.map((line) => [
                slot(line, 'rank'),
                slot(line, 'name'),
                slot(line, 'gross'),
                slot(line, 'played-on'),
                slot(line, 'net'),
            ]),
        ).toEqual([
            ['1', 'Manu', '88 − 23', '19 nov. 2026', '65'],
            ['2', 'Sean', '84 − 16', '19 nov. 2026', '68'],
            ['3', 'Gab', '74 − 2', '18 nov. 2026', '72'],
        ]);
    });

    it('unfolds the combined card without a request, then folds it back', async () => {
        render(<RoundsCompare courses={courses} match={match} />);

        expect(
            screen.queryByRole('table', { name: 'Carte de score' }),
        ).toBeNull();

        await userEvent.click(
            screen.getByRole('button', { name: 'Voir la carte combinée' }),
        );

        const table = screen.getByRole('table', { name: 'Carte de score' });

        expect(
            within(table)
                .getAllByRole('columnheader')
                .map((header) => header.textContent),
        ).toEqual([
            'Trou',
            'Par · idx',
            'GAB18 nov.',
            'MANU19 nov.',
            'SEAN19 nov.',
        ]);
        expect(cardRow('TOTAL · PAR 71')).toEqual([
            'TOTAL · PAR 71',
            '74',
            '88',
            '84',
        ]);
        expect(
            screen.queryByText(
                'Les pars ou les index de trou diffèrent entre ces rounds.',
            ),
        ).toBeNull();

        await userEvent.click(
            screen.getByRole('button', { name: 'Masquer la carte combinée' }),
        );

        expect(
            screen.queryByRole('table', { name: 'Carte de score' }),
        ).toBeNull();
        expect(get).not.toHaveBeenCalled();
    });

    it('notes « Les pars ou les index de trou diffèrent entre ces rounds. » under the card when they differ', async () => {
        render(
            <RoundsCompare
                courses={courses}
                match={{ ...match, holesDiffer: true }}
            />,
        );

        await userEvent.click(
            screen.getByRole('button', { name: 'Voir la carte combinée' }),
        );

        const note = screen.getByText(
            'Les pars ou les index de trou diffèrent entre ces rounds.',
        );

        expect(
            screen
                .getByRole('table', { name: 'Carte de score' })
                .compareDocumentPosition(note) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
    });

    it('visits the address of the new course without picks on a change of course', async () => {
        render(<RoundsCompare courses={courses} match={match} />);

        await userEvent.selectOptions(
            screen.getByRole('combobox', { name: 'Parcours' }),
            'PAYNES VALLEY / Blue',
        );

        expect(get).toHaveBeenCalledTimes(1);
        expect(get).toHaveBeenCalledWith(
            '/rounds/compare?course=PAYNES+VALLEY&tees=Blue',
            {},
            { preserveScroll: true, preserveState: true, replace: true },
        );
    });

    it('shows « Aucun parcours : enregistrez d’abord un round. » in place of the list without course', () => {
        render(<RoundsCompare courses={[]} match={null} />);

        expect(
            screen.getByText("Aucun parcours : enregistrez d'abord un round."),
        ).toBeInTheDocument();
        expect(screen.queryByRole('combobox')).toBeNull();
        expect(screen.queryByText(PROMPT)).toBeNull();
    });
});
