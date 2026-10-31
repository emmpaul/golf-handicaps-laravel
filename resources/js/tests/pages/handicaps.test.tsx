import { router } from '@inertiajs/react';
import {
    fireEvent,
    render,
    screen,
    waitFor,
    within,
} from '@testing-library/react';
import { describe, expect, it, vi } from 'vite-plus/test';

import HandicapsIndex from '@/pages/handicaps/index';
import { GAB, MANU, SEAN } from '@/tests/reference-round';
import { expectPlayerTabs } from '@/tests/player-tabs';
import { expectPrefetchedOnTouchAndHover } from '@/tests/list-link-prefetch';

// Spec handicap-index « Un joueur à la fois sur la page Handicaps », « Résumé
// de l'index d'un joueur », « Liste des rounds d'un joueur », « Marque
// « Compte » sur la ligne du round » (MODIFIED by visual-redesign), « Mise en
// page de la page Handicaps », « Graphique des différentiels » and
// « Lien vers le calcul de l'index ». Head needs the head manager of createInertiaApp
// and renders nothing here; usePage is simulated for the top row.
// Mutations: the « Compte » mark shown on a round that does not count → the
// rows test goes red; the history listed oldest first (`[...history].reverse()`)
// → the rows test goes red; « Compte » moved out of the date element → the
// same-line test goes red; `aria-current` on every player → the tabs test
// goes red; the Next.js subtitle « différentiel = score ajusté − par » put
// back → the link test goes red; the bars scaled to 150 px instead of
// 110 px for the largest differential → the bars test reads 27, 75 and 150 px
// and goes red; the trend computed on the two latest differentials instead
// of the indexes (`deltaT`) → the trend test reads « ▼ 5,0 » and goes red;
// « hcp de jeu » put back in the summary (E34) → the established test goes
// red; the trend shown at the first round (`deltaT ?? 0`) → the first round
// test goes red.

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ url: '/handicaps', props: {} }),
}));

const ROUND_17 = '0190a6b2-7c3e-7000-8000-0000000000a3';
const ROUND_10 = '0190a6b2-7c3e-7000-8000-0000000000a2';
const ROUND_01 = '0190a6b2-7c3e-7000-8000-0000000000a1';

const players = [
    {
        id: GAB,
        name: 'Gab',
        indexT: 100,
        provisional: false,
        deltaT: -85,
        rounds: 3,
        colorSlot: 0,
    },
    {
        id: MANU,
        name: 'Manu',
        indexT: 158,
        provisional: false,
        deltaT: 3,
        rounds: 8,
        colorSlot: 1,
    },
    {
        id: SEAN,
        name: 'Sean',
        indexT: 145,
        provisional: true,
        deltaT: -25,
        rounds: 2,
        colorSlot: 2,
    },
];

/** Gab's rounds, newest first, as PlayerHandicapHistory sends them. */
const gabHistory = [
    {
        roundId: ROUND_17,
        playedOn: '2026-10-17',
        courseName: 'BETHPAGE BLACK',
        tees: 'Red',
        differentialT: 120,
        indexAfterT: 100,
        countsInCurrent: true,
    },
    {
        roundId: ROUND_10,
        playedOn: '2026-10-10',
        courseName: 'PAYNES VALLEY',
        tees: 'Blue',
        differentialT: 170,
        indexAfterT: 185,
        countsInCurrent: false,
    },
    {
        roundId: ROUND_01,
        playedOn: '2026-10-01',
        courseName: 'PAYNES VALLEY',
        tees: 'Blue',
        differentialT: 200,
        indexAfterT: 200,
        countsInCurrent: false,
    },
];

/** One round of the history, the course of the reference card. */
function row(
    playedOn: string,
    differentialT: number,
    indexAfterT: number,
    countsInCurrent: boolean,
) {
    return {
        roundId: `0190a6b2-7c3e-7000-8000-${playedOn.replaceAll('-', '').padStart(12, '0')}`,
        playedOn,
        courseName: 'BETHPAGE BLACK',
        tees: 'Red',
        differentialT,
        indexAfterT,
        countsInCurrent,
    };
}

/** `count` rounds a day apart, newest first, the `counted` newest counting. */
function history(count: number, counted: number, indexAfterT: number) {
    return Array.from({ length: count }, (_, position) =>
        row(
            `2026-${String(10 - Math.floor(position / 28)).padStart(2, '0')}-${String(28 - (position % 28)).padStart(2, '0')}`,
            100 + position,
            indexAfterT,
            position < counted,
        ),
    );
}

function section(): HTMLElement {
    return screen.getByRole('region', { name: /^(Gab|Manu|Sean)$/ });
}

function rows(): HTMLElement[] {
    return within(
        screen.getByRole('list', { name: /^Rounds de / }),
    ).getAllByRole('listitem');
}

describe('Handicaps page', () => {
    it('shows a pill tab per player, the chosen one filled with ink, each a link to /handicaps?joueur=<id>', () => {
        render(
            <HandicapsIndex
                players={players}
                selectedPlayerId={MANU}
                history={[]}
            />,
        );

        const tabs = expectPlayerTabs([
            `/handicaps?joueur=${GAB}`,
            `/handicaps?joueur=${MANU}`,
            `/handicaps?joueur=${SEAN}`,
        ]);
        expect(tabs[1].className).toContain('aria-[current=page]:bg-ink');
        expect(tabs[1].className).toContain('aria-[current=page]:text-bg');
        expect(tabs[1].className).toContain('aria-[current=page]:border-ink');
        expect(tabs[0].className).toContain('border-rule');
        expect(tabs[0].className).toContain('text-ink-2');
        expect(tabs[0].className).toContain('h-11');
        expect(tabs[0].className).toContain('rounded-pill');
        expect(
            tabs[2].querySelector('[aria-hidden="true"]')?.className,
        ).toContain('bg-player-3');
    });

    it('opens Manu on « INDEX ACTUEL », « 15,8 » at 88 px and « ▲ 0,3 » in his colour', () => {
        render(
            <HandicapsIndex
                players={players}
                selectedPlayerId={MANU}
                history={history(8, 8, 160)}
            />,
        );

        const index = within(section()).getByTestId('player-index');
        const trend = within(section()).getByTestId('index-trend');

        expect(within(section()).getByText('Index actuel').className).toContain(
            'uppercase',
        );
        expect(index.textContent).toBe('15,8');
        expect(index.className).toContain('text-display-xl');
        expect(trend.textContent).toBe('▲ 0,3');
        expect(trend.className).toContain('text-player-2');
        expect(within(section()).queryByText('PROVISOIRE')).toBeNull();
    });

    it('takes the trend from the indexes, not from the differentials', () => {
        render(
            <HandicapsIndex
                players={players}
                selectedPlayerId={MANU}
                history={[
                    row('2026-10-17', 120, 158, true),
                    row('2026-10-10', 170, 155, true),
                    row('2026-10-01', 200, 200, true),
                ]}
            />,
        );

        expect(within(section()).getByTestId('index-trend').textContent).toBe(
            '▲ 0,3',
        );
    });

    it('shows Sean provisional: the pill « PROVISOIRE », « 14,5 », « ▼ 2,5 » and « 2 rounds · moyenne des 2 différentiels. »', () => {
        render(
            <HandicapsIndex
                players={players}
                selectedPlayerId={SEAN}
                history={history(2, 2, 145)}
            />,
        );

        const pill = within(section()).getByText('PROVISOIRE');

        expect(pill.className).toContain('text-accent');
        expect(within(section()).getByTestId('player-index').textContent).toBe(
            '14,5',
        );
        expect(within(section()).getByTestId('index-trend').textContent).toBe(
            '▼ 2,5',
        );
        expect(within(section()).getByTestId('index-summary').textContent).toBe(
            '2 rounds · moyenne des 2 différentiels.',
        );
    });

    it('shows no trend at the first round, and « 1 round · moyenne de 1 différentiel. »', () => {
        render(
            <HandicapsIndex
                players={[
                    { ...players[2], indexT: 170, deltaT: null, rounds: 1 },
                ]}
                selectedPlayerId={SEAN}
                history={history(1, 1, 170)}
            />,
        );

        expect(within(section()).getByTestId('player-index').textContent).toBe(
            '17,0',
        );
        expect(within(section()).queryByTestId('index-trend')).toBeNull();
        expect(within(section()).getByTestId('index-summary').textContent).toBe(
            '1 round · moyenne de 1 différentiel.',
        );
    });

    it('writes « = stable » for an index unchanged by the latest round', () => {
        render(
            <HandicapsIndex
                players={[{ ...players[0], deltaT: 0 }]}
                selectedPlayerId={GAB}
                history={history(3, 1, 100)}
            />,
        );

        expect(within(section()).getByTestId('index-trend').textContent).toBe(
            '= stable',
        );
    });

    it('sums up 3 rounds as « 3 rounds · meilleur différentiel sur 3 − 2,0. »', () => {
        render(
            <HandicapsIndex
                players={players}
                selectedPlayerId={GAB}
                history={history(3, 1, 100)}
            />,
        );

        expect(within(section()).getByTestId('index-summary').textContent).toBe(
            '3 rounds · meilleur différentiel sur 3 − 2,0.',
        );
    });

    it('sums up 20 rounds as « 20 rounds · 8 meilleurs différentiels sur 20. », without « hcp de jeu »', () => {
        render(
            <HandicapsIndex
                players={players}
                selectedPlayerId={MANU}
                history={history(20, 8, 158)}
            />,
        );

        const summary = within(section()).getByTestId('index-summary');

        expect(summary.textContent).toBe(
            '20 rounds · 8 meilleurs différentiels sur 20.',
        );
        expect(summary.textContent).not.toContain('hcp de jeu');
    });

    it('sums up 21 rounds on the 20 latest', () => {
        render(
            <HandicapsIndex
                players={players}
                selectedPlayerId={MANU}
                history={history(21, 8, 158)}
            />,
        );

        expect(within(section()).getByTestId('index-summary').textContent).toBe(
            '21 rounds · 8 meilleurs différentiels sur 20.',
        );
        expect(rows()).toHaveLength(21);
    });

    // The bars grow from 4 px over two frames (D8, motion.test.tsx): their
    // heights are read once grown.
    it('draws the differentials 2,0, 5,5 and 11,0 as bars of 20, 55 and 110 px, oldest first, the counting one in the colour of the player', async () => {
        render(
            <HandicapsIndex
                players={players}
                selectedPlayerId={GAB}
                history={[
                    row('2026-10-17', 110, 100, true),
                    row('2026-10-10', 55, 185, false),
                    row('2026-10-01', 20, 200, false),
                ]}
            />,
        );

        const chart = screen.getByRole('list', { name: 'Différentiels' });
        const bars = within(chart).getAllByRole('listitem');

        await waitFor(() =>
            expect(
                bars.map((bar) => [
                    bar.querySelector('[data-part="value"]')?.textContent,
                    (bar.querySelector('[data-part="bar"]') as HTMLElement)
                        .style.height,
                    bar.querySelector('[data-part="date"]')?.textContent,
                ]),
            ).toEqual([
                ['2', '20px', '1/10'],
                ['5,5', '55px', '10/10'],
                ['11', '110px', '17/10'],
            ]),
        );
        expect(
            bars.map(
                (bar) => bar.querySelector('[data-part="bar"]')?.className,
            ),
        ).toEqual([
            expect.stringContaining('bg-bar'),
            expect.stringContaining('bg-bar'),
            expect.stringContaining('bg-player-1'),
        ]);
    });

    it('gives a zero or negative differential a bar of 4 px', async () => {
        render(
            <HandicapsIndex
                players={players}
                selectedPlayerId={GAB}
                history={[
                    row('2026-10-17', -12, 100, true),
                    row('2026-10-10', 0, 185, false),
                    row('2026-10-01', 80, 200, false),
                ]}
            />,
        );

        await waitFor(() =>
            expect(
                within(screen.getByRole('list', { name: 'Différentiels' }))
                    .getAllByRole('listitem')
                    .map(
                        (bar) =>
                            (
                                bar.querySelector(
                                    '[data-part="bar"]',
                                ) as HTMLElement
                            ).style.height,
                    ),
            ).toEqual(['110px', '4px', '4px']),
        );
    });

    it('lists the rounds newest first, « Compte » on the counting ones only', () => {
        render(
            <HandicapsIndex
                players={players}
                selectedPlayerId={GAB}
                history={gabHistory}
            />,
        );

        expect(
            rows().map((item) => [
                within(item).getByTestId('round-date').textContent,
                within(item).queryByRole('img', { name: 'Compte' }) !== null,
                within(item).getByTestId('round-differential').textContent,
                within(item).getByTestId('round-index-after').textContent,
            ]),
        ).toEqual([
            ['17 oct. 2026', true, '12,0', '10,0'],
            ['10 oct. 2026', false, '17,0', '18,5'],
            ['1 oct. 2026', false, '20,0', '20,0'],
        ]);
    });

    it('keeps the « Compte » dot of 6 px in the colour of the player inside the date element', () => {
        render(
            <HandicapsIndex
                players={players}
                selectedPlayerId={GAB}
                history={gabHistory}
            />,
        );

        const date = within(rows()[0]).getByTestId('round-date');
        const dot = within(date).getByRole('img', { name: 'Compte' });

        expect(date.className).toContain('whitespace-nowrap');
        expect(dot.className).toContain('size-1.5');
        expect(dot.className).toContain('bg-player-1');
    });

    it('shows « BETHPAGE BLACK · Red » in grey under the date, each row a link to its round', () => {
        render(
            <HandicapsIndex
                players={players}
                selectedPlayerId={GAB}
                history={gabHistory}
            />,
        );

        const [first] = rows();
        const course = within(first).getByText('BETHPAGE BLACK · Red');

        expect(course.className).toContain('text-body-12');
        expect(course.className).toContain('truncate');
        expect(
            within(first).getByTestId('round-index-after').className,
        ).toContain('text-ink-2');
        expect(within(first).getByRole('link').className).toContain('min-h-13');
        expect(
            rows().map((item) =>
                within(item).getByRole('link').getAttribute('href'),
            ),
        ).toEqual([
            `/rounds/${ROUND_17}`,
            `/rounds/${ROUND_10}`,
            `/rounds/${ROUND_01}`,
        ]);
    });

    it('shows « — » at 88 px and the text of the design for a player without round', () => {
        render(
            <HandicapsIndex
                players={[
                    {
                        id: SEAN,
                        name: 'Sean',
                        indexT: null,
                        provisional: false,
                        deltaT: null,
                        rounds: 0,
                        colorSlot: 2,
                    },
                ]}
                selectedPlayerId={SEAN}
                history={[]}
            />,
        );

        const index = within(section()).getByTestId('player-index');

        expect(index.textContent).toBe('—');
        expect(index.className).toContain('text-display-xl');
        expect(
            within(section()).getByText(
                "Pas encore d'index. Un premier round donne un index provisoire.",
            ),
        ).toBeInTheDocument();
        expect(within(section()).queryByTestId('index-trend')).toBeNull();
        expect(within(section()).queryByText('Index actuel')).toBeNull();
    });

    // Spec handicap-index « Lien vers le calcul de l'index », scenario « Page
    // Handicaps »: the link keeps the chosen player, no rule line on the page.
    // Mutations: `playerId` not passed (the link reads /handicaps/calcul), the
    // former folded block kept, or the Next.js subtitle put back → red.
    it('links « Comment l’index est calculé » to the chosen player, without the rule lines nor the Next.js subtitle', () => {
        render(
            <HandicapsIndex
                players={players}
                selectedPlayerId={MANU}
                history={gabHistory}
            />,
        );

        expect(
            screen
                .getByRole('link', { name: 'Comment l’index est calculé' })
                .getAttribute('href'),
        ).toBe(`/handicaps/calcul?joueur=${MANU}`);
        expect(
            screen.queryByText(
                'Score ajusté : chaque trou compte au plus par + 4.',
            ),
        ).toBeNull();
        expect(
            screen.queryByText(
                'Parcours sans rating : la course rating vaut le par du round et le slope 113.',
            ),
        ).toBeNull();
        expect(
            screen.queryByText(/différentiel = score ajusté − par/),
        ).toBeNull();
    });

    // Spec « Un joueur à la fois sur la page Handicaps » and « Changement de
    // joueur sur la page Handicaps » (D8, D13): the visit keeps the page (its
    // index swaps in place) and reloads the history and the stats of the new
    // player. Mutations: `scoringStats` left out of `only` (the stats of the
    // former player stay) or `preserveState` removed (the page remounts and
    // the swap never plays) → this test goes red.
    it('reloads the history and the stats of the chosen player, keeping the page', () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        render(
            <HandicapsIndex
                players={players}
                selectedPlayerId={GAB}
                history={gabHistory}
            />,
        );

        fireEvent.click(screen.getByRole('link', { name: /Manu/ }));

        expect(visit).toHaveBeenCalledTimes(1);
        expect(visit.mock.calls[0][1]).toMatchObject({
            only: ['selectedPlayerId', 'history', 'scoringStats'],
            preserveState: true,
            preserveScroll: true,
        });
        visit.mockRestore();
    });

    // Spec « Mise en page de la page Handicaps »: the STATS block sits between
    // the chart of the differentials and the history; the deferred prop not
    // loaded yet shows no block. Mutations: the block rendered after the
    // history, or not rendered → this test goes red.
    it('places the STATS block under the chart and above the history', () => {
        const { rerender } = render(
            <HandicapsIndex
                players={players}
                selectedPlayerId={GAB}
                history={gabHistory}
            />,
        );

        expect(screen.queryByRole('heading', { name: 'Stats' })).toBeNull();

        rerender(
            <HandicapsIndex
                players={players}
                selectedPlayerId={GAB}
                history={gabHistory}
                scoringStats={{
                    rounds: 3,
                    holes: 54,
                    averageGrossT: 800,
                    bestGross: 72,
                    parOrBetterPercent: 56,
                    classes: [0, 6, 24, 16, 8],
                    classPercents: [0, 11, 44, 30, 15],
                    pars: [],
                }}
            />,
        );

        const headings = screen
            .getAllByRole('heading', { level: 2 })
            .map((heading) => heading.textContent);
        const stats = screen.getByRole('heading', { name: 'Stats' });
        const list = screen.getByRole('list', { name: 'Rounds de Gab' });

        expect(headings.indexOf('Stats')).toBe(
            headings.indexOf('Différentiels') + 1,
        );
        expect(
            stats.compareDocumentPosition(list) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
        expect(screen.getByText('54 trous')).toBeTruthy();
    });

    // Change of player (D8, DO31): the chart and the STATS block are keyed by
    // player so they replay; with the same key on both siblings, React kept
    // the former chart beside the new one. Mutation: `key={player.id}` on
    // both DifferentialBars and ScoringStats → two « Différentiels » headings,
    // this test goes red.
    it('shows one chart only after switching from Gab to Manu', () => {
        const { rerender } = render(
            <HandicapsIndex
                players={players}
                selectedPlayerId={GAB}
                history={gabHistory}
            />,
        );

        rerender(
            <HandicapsIndex
                players={players}
                selectedPlayerId={MANU}
                history={history(8, 8, 158)}
            />,
        );

        expect(
            screen.getAllByRole('heading', { name: 'Différentiels' }),
        ).toHaveLength(1);
    });

    it('shows « Aucun joueur. » without tabs when there is no player', () => {
        render(
            <HandicapsIndex
                players={[]}
                selectedPlayerId={null}
                history={[]}
            />,
        );

        expect(screen.getByText('Aucun joueur.').textContent).toBe(
            'Aucun joueur.',
        );
        expect(
            screen.queryByRole('navigation', { name: 'Choisir un joueur' }),
        ).toBeNull();
    });

    // Spec handicap-index « Lien vers la page Coups », scenarios « Lien
    // affiché » and « Sans joueur »: « Coups à donner » on the title line, to
    // /strokes without the chosen player. Mutations: the link shown only with
    // a player → red on the empty list; `?joueur=` added → red on Gab.
    it('links « Coups à donner » to /strokes, without the chosen player, on the line of the title', () => {
        render(
            <HandicapsIndex
                players={players.slice(0, 2)}
                selectedPlayerId={GAB}
                history={[]}
            />,
        );

        const link = screen.getByRole('link', { name: 'Coups à donner' });

        expect(link.getAttribute('href')).toBe('/strokes');
        expect(link.parentElement?.className).toBe(
            'flex items-end justify-between gap-3',
        );
        expect(
            within(link.parentElement as HTMLElement).getByRole('heading', {
                level: 1,
            }).textContent,
        ).toBe('Handicaps');
    });

    it('links « Coups à donner » to /strokes when there is no player', () => {
        render(
            <HandicapsIndex
                players={[]}
                selectedPlayerId={null}
                history={[]}
            />,
        );

        expect(screen.getByText('Aucun joueur.').textContent).toBe(
            'Aucun joueur.',
        );
        expect(
            screen
                .getByRole('link', { name: 'Coups à donner' })
                .getAttribute('href'),
        ).toBe('/strokes');
    });
});

// Spec app-shell « Liens de liste préchargés » (design D5): a round of the
// history of a player prefetches its page like a round of the list.
// Mutations: the `listLinkPrefetch` props dropped from the link → no call on
// touch nor on hover, red; `onTouchStart` dropped → no call on touch, red.
describe('Round history link prefetch', () => {
    it('prefetches the round of the history on touch and on hover', async () => {
        render(
            <HandicapsIndex
                players={players}
                selectedPlayerId={MANU}
                history={history(1, 1, 158)}
            />,
        );

        await expectPrefetchedOnTouchAndHover(
            within(rows()[0]).getByRole('link'),
            '/rounds/0190a6b2-7c3e-7000-8000-000020261028',
        );
    });
});
