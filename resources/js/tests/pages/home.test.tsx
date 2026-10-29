import { render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vite-plus/test';

import Home from '@/pages/home';
import { GAB, MANU, ROUND, SEAN } from '@/tests/reference-round';

// Spec home-dashboard « Action d'import en tête », « Index des joueurs »,
// « Dernier round » and « Imports à vérifier ». Head needs the head manager of
// createInertiaApp and renders nothing here.
// Mutations: « imports » written in the singular for 2 → the « 2 imports à
// vérifier » test goes red; a delta shown at the first round
// (`formatSignedTenths(deltaT ?? 0)`, « = ») → the provisional test goes red;
// « · » put between course and tees → the latest round test goes red; the
// pending block shown at 0 → the « no block » test goes red.

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    Head: () => null,
}));

const ALEX = '0190a6b2-7c3e-7000-8000-000000000004';
const IMPORT = '0190a6b2-7c3e-7000-8000-0000000000bb';

const players = [
    { id: ALEX, name: 'alex', indexT: 170, provisional: true, deltaT: null },
    { id: GAB, name: 'Gab', indexT: 5, provisional: false, deltaT: 0 },
    { id: MANU, name: 'Manu', indexT: 158, provisional: false, deltaT: 3 },
    { id: SEAN, name: 'Sean', indexT: null, provisional: false, deltaT: null },
];

const latestRound = {
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

const noImport = { count: 0, latestId: null };

/** The row of one player in « Index des joueurs ». */
function playerRow(name: string): HTMLElement {
    const list = screen.getByRole('list', { name: 'Index des joueurs' });

    return within(list)
        .getByText(name, { exact: true })
        .closest('li') as HTMLElement;
}

/** The delta cell of one player's row. */
function delta(name: string): string {
    return (
        within(playerRow(name)).getByTestId('player-delta').textContent ?? ''
    );
}

function latestSection(): HTMLElement {
    return screen.getByRole('region', { name: 'Dernier round' });
}

describe('Home page', () => {
    it('opens with « Handicaps golf » and « Importer une carte » to /import', () => {
        render(
            <Home
                players={players}
                latestRound={latestRound}
                pendingImports={noImport}
            />,
        );

        expect(screen.getByRole('heading', { level: 1 }).textContent).toBe(
            'Handicaps golf',
        );
        expect(
            screen
                .getByRole('link', { name: 'Importer une carte' })
                .getAttribute('href'),
        ).toBe('/import');
    });

    it('no longer shows the sections nor « Le tableau du groupe arrive bientôt. »', () => {
        render(
            <Home
                players={players}
                latestRound={latestRound}
                pendingImports={noImport}
            />,
        );

        expect(screen.queryByText('Sections')).toBeNull();
        expect(
            screen.queryByText('Le tableau du groupe arrive bientôt.'),
        ).toBeNull();
    });

    it('shows Manu « 15,8 » and « +0,3 », Gab « 0,5 » and « = »', () => {
        render(
            <Home
                players={players}
                latestRound={latestRound}
                pendingImports={noImport}
            />,
        );

        expect(within(playerRow('Manu')).getByText('15,8').textContent).toBe(
            '15,8',
        );
        expect(delta('Manu')).toBe('+0,3');
        expect(within(playerRow('Gab')).getByText('0,5').textContent).toBe(
            '0,5',
        );
        expect(delta('Gab')).toBe('=');
    });

    it('shows Sean « pas encore d’index » without delta', () => {
        render(
            <Home
                players={players}
                latestRound={latestRound}
                pendingImports={noImport}
            />,
        );

        expect(
            within(playerRow('Sean')).getByText("pas encore d'index")
                .textContent,
        ).toBe("pas encore d'index");
        expect(delta('Sean')).toBe('');
    });

    it('marks a provisional index « provisoire », without delta at the first round', () => {
        render(
            <Home
                players={players}
                latestRound={latestRound}
                pendingImports={noImport}
            />,
        );

        expect(
            within(playerRow('alex')).getByText('provisoire').textContent,
        ).toBe('provisoire');
        expect(within(playerRow('Manu')).queryByText('provisoire')).toBeNull();
        expect(delta('alex')).toBe('');
    });

    it('leads each player to /handicaps?joueur=<id>, in the order received', () => {
        render(
            <Home
                players={players}
                latestRound={latestRound}
                pendingImports={noImport}
            />,
        );

        const list = screen.getByRole('list', { name: 'Index des joueurs' });

        expect(
            within(list)
                .getAllByRole('link')
                .map((link) => link.getAttribute('href')),
        ).toEqual([
            `/handicaps?joueur=${ALEX}`,
            `/handicaps?joueur=${GAB}`,
            `/handicaps?joueur=${MANU}`,
            `/handicaps?joueur=${SEAN}`,
        ]);
    });

    it('shows the latest round, without « · », as a link to its page', () => {
        render(
            <Home
                players={players}
                latestRound={latestRound}
                pendingImports={noImport}
            />,
        );

        const link = within(latestSection()).getByRole('link');

        expect(link.getAttribute('href')).toBe(`/rounds/${ROUND}`);
        expect(within(link).getByText('BETHPAGE BLACK').textContent).toBe(
            'BETHPAGE BLACK',
        );
        expect(within(link).getByText('Tees Red').textContent).toBe('Tees Red');
        expect(within(link).getByText('17 octobre 2026').tagName).toBe('TIME');
        expect(
            within(link)
                .getAllByRole('listitem')
                .map((item) => item.textContent),
        ).toEqual(['Gab 72', 'Manu 88']);
        expect(within(link).getByText(/^Gagnant net/).textContent).toBe(
            'Gagnant net : Gab',
        );
        expect(link.textContent).not.toContain('·');
    });

    it('shows no « Gagnant net » line when nobody had an index', () => {
        render(
            <Home
                players={players}
                latestRound={{ ...latestRound, netWinners: [] }}
                pendingImports={noImport}
            />,
        );

        expect(within(latestSection()).queryByText(/Gagnant net/)).toBeNull();
    });

    it('shows « Aucun round enregistré » without round', () => {
        render(
            <Home
                players={players}
                latestRound={null}
                pendingImports={noImport}
            />,
        );

        expect(
            within(latestSection()).getByText('Aucun round enregistré')
                .textContent,
        ).toBe('Aucun round enregistré');
        expect(within(latestSection()).queryByRole('link')).toBeNull();
    });

    it('links « 1 import à vérifier » to the correction screen of the latest', () => {
        render(
            <Home
                players={players}
                latestRound={latestRound}
                pendingImports={{ count: 1, latestId: IMPORT }}
            />,
        );

        expect(
            screen
                .getByRole('link', { name: '1 import à vérifier' })
                .getAttribute('href'),
        ).toBe(`/imports/${IMPORT}`);
    });

    it('writes « 2 imports à vérifier »', () => {
        render(
            <Home
                players={players}
                latestRound={latestRound}
                pendingImports={{ count: 2, latestId: IMPORT }}
            />,
        );

        expect(
            screen.getByRole('link', { name: '2 imports à vérifier' })
                .textContent,
        ).toBe('2 imports à vérifier');
    });

    it('shows no block « à vérifier » without import to check', () => {
        render(
            <Home
                players={players}
                latestRound={latestRound}
                pendingImports={noImport}
            />,
        );

        expect(screen.queryByText(/à vérifier/)).toBeNull();
    });

    it('keeps the logout button', () => {
        render(
            <Home
                players={players}
                latestRound={latestRound}
                pendingImports={noImport}
            />,
        );

        expect(
            screen.getByRole('button', { name: 'Se déconnecter' }).textContent,
        ).toBe('Se déconnecter');
    });
});
