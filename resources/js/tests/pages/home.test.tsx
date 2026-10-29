import { render, screen, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';

import Home from '@/pages/home';
import type { LatestRound, PlayerStanding } from '@/pages/home';
import { GAB, MANU, ROUND, SEAN } from '@/tests/reference-round';

// Spec home-dashboard « Action d'import en tête », « Index des joueurs »,
// « Dernier round », « Imports à vérifier » (MODIFIED by visual-redesign) and
// « Mise en page de l'accueil ». Head needs the head manager of
// createInertiaApp and renders nothing here; usePage is simulated with the
// shared deferred prop `pendingImports` (design D7, DO17).
// Mutations:
// - « 1 coups » (the plural kept for a gap of 1) → the one stroke test goes
//   red;
// - the player without index ranked first (`rank` counted on every line, the
//   rows sorted index ascending with `null` as 0) → the state lines test reads
//   « 1 » on Alex and goes red;
// - « imports » written in the singular for 2 → the two imports test goes red;
// - the imports row leading to the correction screen instead of /import → the
//   imports tests go red;
// - the imports row shown at 0 → the « no row » test goes red;
// - the trend shown at the first round (`deltaT ?? 0`, « = STABLE ») → the
//   state lines test reads « = STABLE · PROVISOIRE » on Sean and goes red;
// - the result line kept when nobody is ranked → the « nobody has an index »
//   test goes red;
// - the latest round block kept without round (« Aucun round enregistré ») →
//   the « no round » test goes red.

const page = vi.hoisted(() => ({ props: {} as Record<string, unknown> }));

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ url: '/', props: page.props }),
}));

const ALEX = '0190a6b2-7c3e-7000-8000-000000000004';

/** Scenario « Lignes d'état »: the order of the server (PlayerStandings::ranked). */
const players: PlayerStanding[] = [
    {
        id: GAB,
        name: 'Gab',
        indexT: 25,
        provisional: false,
        deltaT: -15,
        rounds: 8,
        colorSlot: 0,
    },
    {
        id: MANU,
        name: 'Manu',
        indexT: 165,
        provisional: false,
        deltaT: 0,
        rounds: 8,
        colorSlot: 1,
    },
    {
        id: SEAN,
        name: 'Sean',
        indexT: 190,
        provisional: true,
        deltaT: null,
        rounds: 1,
        colorSlot: 2,
    },
    {
        id: ALEX,
        name: 'Alex',
        indexT: null,
        provisional: false,
        deltaT: null,
        rounds: 0,
        colorSlot: 3,
    },
];

/** A line of the leaderboard of the latest round, in the order of the card. */
function entry(
    name: string,
    gross: number,
    playingHandicap: number | null,
    net: number | null,
    rank: number | null,
    colorSlot = 0,
) {
    return {
        playerId: `${name}-id`,
        name,
        colorSlot,
        gross,
        indexBeforeT: playingHandicap === null ? null : 10,
        playingHandicap,
        net,
        rank,
    };
}

/** Scenario « Dernier round affiché »: Gab first on the card, Manu first in net. */
const latestRound: LatestRound = {
    id: ROUND,
    playedOn: '2026-10-17',
    courseName: 'BETHPAGE BLACK',
    tees: 'Red',
    leaderboard: [
        entry('Gab', 72, 2, 70, 2, 0),
        entry('Manu', 88, 23, 65, 1, 1),
    ],
};

beforeEach(() => {
    page.props = {};
});

/** The lines of the « INDEX » board, each as the texts of its parts. */
function boardLines(): string[][] {
    return within(screen.getByRole('list', { name: 'Index' }))
        .getAllByRole('listitem')
        .map((item) =>
            [...item.querySelectorAll('[data-part]')].map(
                (part) => part.textContent ?? '',
            ),
        );
}

function latestBlock(): HTMLElement {
    return screen.getByRole('region', { name: 'Dernier round' });
}

/** The lines of the latest round, each as the texts of its parts. */
function latestLines(): string[][] {
    return within(latestBlock())
        .getAllByRole('listitem')
        .map((item) =>
            [...item.querySelectorAll('[data-part]')].map(
                (part) => part.textContent ?? '',
            ),
        );
}

/** The result line « Net · … » of the latest round, or null. */
function result(leaderboard: LatestRound['leaderboard']): string | null {
    render(
        <Home
            players={players}
            latestRound={{ ...latestRound, leaderboard }}
        />,
    );

    return (
        within(latestBlock()).queryByTestId('latest-result')?.textContent ??
        null
    );
}

describe('Home page', () => {
    it('opens on « Golf » and « Se déconnecter », without title nor import button of its own', () => {
        render(<Home players={players} latestRound={latestRound} />);

        expect(screen.getByText('Golf').tagName).toBe('SPAN');
        expect(
            screen
                .getByRole('button', { name: 'Se déconnecter' })
                .closest('form')
                ?.getAttribute('action'),
        ).toBe('/logout');
        expect(screen.queryByRole('heading', { level: 1 })).toBeNull();
        expect(screen.queryByText('Handicaps golf')).toBeNull();
        expect(screen.queryByText('Importer une carte')).toBeNull();
        expect(screen.queryByText('Sections')).toBeNull();
        expect(
            screen.queryByText('Le tableau du groupe arrive bientôt.'),
        ).toBeNull();
    });

    it('writes the state lines of the scenario, the players in the order received', () => {
        render(<Home players={players} latestRound={latestRound} />);

        expect(boardLines()).toEqual([
            ['1', 'Gab', '▼ 1,5 · 8 ROUNDS', '2,5'],
            ['2', 'Manu', '= STABLE · 8 ROUNDS', '16,5'],
            ['3', 'Sean', 'PROVISOIRE', '19,0'],
            ['–', 'Alex', "PAS ENCORE D'INDEX", '—'],
        ]);
    });

    it('writes « ▲ 0,3 » for an index going up and « ▼ 2,5 · PROVISOIRE » for a provisional one going down', () => {
        render(
            <Home
                players={[
                    { ...players[1], indexT: 158, deltaT: 3 },
                    { ...players[2], indexT: 145, deltaT: -25, rounds: 2 },
                ]}
                latestRound={latestRound}
            />,
        );

        expect(boardLines()).toEqual([
            ['1', 'Manu', '▲ 0,3 · 8 ROUNDS', '15,8'],
            ['2', 'Sean', '▼ 2,5 · PROVISOIRE', '14,5'],
        ]);
    });

    it('draws a board line with its swatch, the name at 17 px and the index at 44 px', () => {
        render(<Home players={players} latestRound={latestRound} />);

        const gab = within(
            screen.getByRole('list', { name: 'Index' }),
        ).getAllByRole('listitem')[0];
        const parts = [...gab.querySelectorAll('[data-part]')];

        expect(gab.querySelector('[aria-hidden="true"]')?.className).toContain(
            'bg-player-1',
        );
        expect(parts[1].className).toContain('text-name-m-17');
        expect(parts[2].className).toContain('text-section');
        expect(parts[3].className).toContain('text-display');
        expect(within(gab).getByRole('link').className).toContain('min-h-19');
    });

    it('leads each player to /handicaps?joueur=<id>', () => {
        render(<Home players={players} latestRound={latestRound} />);

        expect(
            within(screen.getByRole('list', { name: 'Index' }))
                .getAllByRole('link')
                .map((link) => link.getAttribute('href')),
        ).toEqual([
            `/handicaps?joueur=${GAB}`,
            `/handicaps?joueur=${MANU}`,
            `/handicaps?joueur=${SEAN}`,
            `/handicaps?joueur=${ALEX}`,
        ]);
    });

    it('shows « DERNIER ROUND », « 17 OCT. », the course « · Red » and the players by net, as a link to the round', () => {
        render(<Home players={players} latestRound={latestRound} />);

        const block = latestBlock();
        const link = within(block).getByRole('link');

        expect(link.getAttribute('href')).toBe(`/rounds/${ROUND}`);
        expect(within(block).getByText('Dernier round').className).toContain(
            'uppercase',
        );
        expect(within(block).getByText('17 OCT.').tagName).toBe('TIME');
        expect(within(block).getByText('BETHPAGE BLACK').className).toContain(
            'text-name-l-course',
        );
        expect(within(block).getByText('· Red').className).toContain(
            'text-ink-2',
        );
        expect(latestLines()).toEqual([
            ['1', 'Manu', '88 − 23', '65'],
            ['2', 'Gab', '72 − 2', '70'],
        ]);
        expect(within(block).getByTestId('latest-result').textContent).toBe(
            'Net · Manu gagne de 5 coups',
        );
    });

    it('says « Gab gagne de 2 coups »', () => {
        expect(
            result([entry('Gab', 72, 4, 68, 1), entry('Sean', 80, 10, 70, 2)]),
        ).toBe('Net · Gab gagne de 2 coups');
    });

    it('says « Gab gagne de 1 coup » for a gap of one stroke', () => {
        expect(
            result([entry('Gab', 72, 2, 70, 1), entry('Manu', 88, 17, 71, 2)]),
        ).toBe('Net · Gab gagne de 1 coup');
    });

    it('says « Égalité : Gab, Manu » for winners tied, in the order of the card', () => {
        expect(
            result([entry('Gab', 72, 1, 71, 1), entry('Manu', 88, 17, 71, 1)]),
        ).toBe('Net · Égalité : Gab, Manu');
    });

    it('says « Gab seul en jeu » when Gab alone is ranked', () => {
        expect(
            result([
                entry('Gab', 72, 1, 71, 1),
                entry('Manu', 88, null, null, null),
            ]),
        ).toBe('Net · Gab seul en jeu');
    });

    it('writes « 88 · pas encore d’index » and « — » after the ranked players, and no result when nobody has an index', () => {
        render(
            <Home
                players={players}
                latestRound={{
                    ...latestRound,
                    leaderboard: [
                        entry('Gab', 72, null, null, null),
                        entry('Manu', 88, null, null, null),
                    ],
                }}
            />,
        );

        expect(latestLines()).toEqual([
            ['', 'Gab', "72 · pas encore d'index", '—'],
            ['', 'Manu', "88 · pas encore d'index", '—'],
        ]);
        expect(within(latestBlock()).queryByTestId('latest-result')).toBeNull();
    });

    it('shows no « DERNIER ROUND » block without round', () => {
        render(<Home players={players} latestRound={null} />);

        expect(
            screen.queryByRole('region', { name: 'Dernier round' }),
        ).toBeNull();
        expect(screen.queryByText('Aucun round enregistré')).toBeNull();
    });

    it('shows « 2 » « imports à vérifier » leading to /import, from the shared count', () => {
        page.props = { pendingImports: 2 };

        render(<Home players={players} latestRound={latestRound} />);

        const row = screen.getByRole('link', { name: /imports à vérifier/ });

        expect(row.getAttribute('href')).toBe('/import');
        expect(row.textContent).toBe('2imports à vérifier→');
        expect(row.className).toContain('text-accent');
        expect(row.className).toContain('min-h-11');
    });

    it('writes « 1 » « import à vérifier » in the singular', () => {
        page.props = { pendingImports: 1 };

        render(<Home players={players} latestRound={latestRound} />);

        expect(
            screen.getByRole('link', { name: /import à vérifier/ }).textContent,
        ).toBe('1import à vérifier→');
    });

    it('shows no imports row at 0 nor while the shared count is not loaded', () => {
        page.props = { pendingImports: 0 };
        const { unmount } = render(
            <Home players={players} latestRound={latestRound} />,
        );
        expect(screen.queryByText(/à vérifier/)).toBeNull();
        unmount();

        page.props = {};
        render(<Home players={players} latestRound={latestRound} />);
        expect(screen.queryByText(/à vérifier/)).toBeNull();
    });
});
