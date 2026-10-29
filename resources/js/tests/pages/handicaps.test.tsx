import { render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vite-plus/test';

import HandicapsIndex from '@/pages/handicaps/index';
import { GAB, MANU, SEAN } from '@/tests/reference-round';

// Spec handicap-index « Un joueur à la fois sur la page Handicaps », « Résumé
// de l'index d'un joueur », « Liste des rounds d'un joueur », « Marque
// « Compte » sur la ligne du round » and « Explication du calcul ». Head needs
// the head manager of createInertiaApp and renders nothing here.
// Mutations: the « Compte » mark shown on a round that does not count → the
// rows test goes red; the history listed oldest first (`[...history].reverse()`)
// → the rows test goes red; « Compte » moved out of the date element → the
// same-line test goes red; `aria-current` on every player → the picker test
// goes red; the Next.js subtitle « différentiel = score ajusté − par » put
// back → the explanation test goes red.

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    Head: () => null,
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

function section(): HTMLElement {
    return screen.getByRole('region', { name: /^(Gab|Manu|Sean)$/ });
}

function rows(): HTMLElement[] {
    return within(
        screen.getByRole('list', { name: /^Rounds de / }),
    ).getAllByRole('listitem');
}

describe('Handicaps page', () => {
    it('lists the players as links to /handicaps?joueur=<id>, the chosen one current', () => {
        render(
            <HandicapsIndex
                players={players}
                selectedPlayerId={MANU}
                history={[]}
            />,
        );

        const picker = screen.getByRole('navigation', {
            name: 'Choisir un joueur',
        });
        const links = within(picker).getAllByRole('link');

        expect(links.map((link) => link.textContent)).toEqual([
            'Gab',
            'Manu',
            'Sean',
        ]);
        expect(links.map((link) => link.getAttribute('href'))).toEqual([
            `/handicaps?joueur=${GAB}`,
            `/handicaps?joueur=${MANU}`,
            `/handicaps?joueur=${SEAN}`,
        ]);
        expect(links.map((link) => link.getAttribute('aria-current'))).toEqual([
            null,
            'page',
            null,
        ]);
    });

    it('opens the chosen player with « Manu », « 15,8 » and « Écart +0,3 »', () => {
        render(
            <HandicapsIndex
                players={players}
                selectedPlayerId={MANU}
                history={[]}
            />,
        );

        expect(
            within(section()).getByRole('heading', { level: 2 }).textContent,
        ).toBe('Manu');
        expect(within(section()).getByTestId('player-index').textContent).toBe(
            '15,8',
        );
        expect(within(section()).getByTestId('index-delta').textContent).toBe(
            'Écart +0,3',
        );
        expect(within(section()).queryByText('provisoire')).toBeNull();
    });

    it('shows a provisional index « 14,5 », « Écart −2,5 » and « provisoire »', () => {
        render(
            <HandicapsIndex
                players={players}
                selectedPlayerId={SEAN}
                history={[]}
            />,
        );

        expect(within(section()).getByTestId('player-index').textContent).toBe(
            '14,5',
        );
        expect(within(section()).getByTestId('index-delta').textContent).toBe(
            'Écart −2,5',
        );
        expect(within(section()).getByText('provisoire').textContent).toBe(
            'provisoire',
        );
    });

    it('shows « pas encore d’index », no delta and « Aucun round pour l’instant. » without round', () => {
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

        expect(within(section()).getByTestId('player-index').textContent).toBe(
            "pas encore d'index",
        );
        expect(within(section()).queryByTestId('index-delta')).toBeNull();
        expect(
            within(section()).getByText('Aucun round pour l’instant.')
                .textContent,
        ).toBe('Aucun round pour l’instant.');
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
            rows().map((row) => [
                within(row).getByTestId('round-date').textContent,
                within(row).getByTestId('round-differential').textContent,
                within(row).getByTestId('round-index-after').textContent,
            ]),
        ).toEqual([
            ['17 oct. 2026Compte', '12,0', '10,0'],
            ['10 oct. 2026', '17,0', '18,5'],
            ['1 oct. 2026', '20,0', '20,0'],
        ]);
    });

    it('keeps « Compte » inside the date element of its row', () => {
        render(
            <HandicapsIndex
                players={players}
                selectedPlayerId={GAB}
                history={gabHistory}
            />,
        );

        const date = within(rows()[0]).getByTestId('round-date');

        expect(within(date).getByText('17 oct. 2026').textContent).toBe(
            '17 oct. 2026',
        );
        expect(within(date).getByText('Compte').textContent).toBe('Compte');
    });

    it('shows the course and tees apart, without « · », each row a link to its round', () => {
        render(
            <HandicapsIndex
                players={players}
                selectedPlayerId={GAB}
                history={gabHistory}
            />,
        );

        const [first] = rows();

        expect(within(first).getByText('BETHPAGE BLACK').textContent).toBe(
            'BETHPAGE BLACK',
        );
        expect(within(first).getByText('Red').textContent).toBe('Red');
        expect(first.textContent).not.toContain('·');
        expect(
            rows().map((row) =>
                within(row).getByRole('link').getAttribute('href'),
            ),
        ).toEqual([
            `/rounds/${ROUND_17}`,
            `/rounds/${ROUND_10}`,
            `/rounds/${ROUND_01}`,
        ]);
    });

    it('offers « Comment l’index est calculé », closed, without the Next.js subtitle', () => {
        render(
            <HandicapsIndex
                players={players}
                selectedPlayerId={GAB}
                history={gabHistory}
            />,
        );

        const title = screen.getByText('Comment l’index est calculé');

        expect(title.tagName).toBe('SUMMARY');
        expect((title.closest('details') as HTMLDetailsElement).open).toBe(
            false,
        );
        expect(
            screen.queryByText(/différentiel = score ajusté − par/),
        ).toBeNull();
    });

    it('shows « Aucun joueur. » without picker when there is no player', () => {
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
        expect(screen.queryByRole('navigation')).toBeNull();
    });
});
