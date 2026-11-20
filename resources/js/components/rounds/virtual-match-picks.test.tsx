import { router } from '@inertiajs/react';
import type { MockInstance } from 'vite-plus/test';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';

import { VirtualMatchPicks } from '@/components/rounds/virtual-match-picks';
import type {
    VirtualMatchCandidate,
    VirtualMatchPick,
} from '@/components/rounds/virtual-match-picks';
import { GAB, MANU, SEAN } from '@/tests/reference-round';

// Spec virtual-match « Pastilles des joueurs-rounds choisis », « Liste
// d'ajout », « Un seul round par joueur » and design D5: each change of the
// choice is an Inertia GET visit to the address of the new choice. Only
// router.get is stubbed (jsdom cannot run a visit); the popover is the real
// one of components/ui.
// Mutations:
// - the accessible name without the year (« Retirer Gab, 18 nov. ») → the
//   pill test goes red;
// - the pill that removes every pick of the round (Manu and Sean share it)
//   instead of its own → the removal test reads no pick of Manu;
// - an added pick put first instead of last → the addition test reads Sean
//   before Gab;
// - the list left open after an addition → the addition test finds the list;
// - a row of an already picked player left enabled → the « déjà choisi » test
//   goes red;
// - the visit sent on close → the close test sees router.get called.

const BETHPAGE_RED_11_18 = '0190a6b2-7c3e-7000-8000-0000000000b1';
const BETHPAGE_RED_11_19 = '0190a6b2-7c3e-7000-8000-0000000000b2';
const BETHPAGE_RED_11_02 = '0190a6b2-7c3e-7000-8000-0000000000b3';

const picks: VirtualMatchPick[] = [
    {
        roundId: BETHPAGE_RED_11_18,
        playerId: GAB,
        name: 'Gab',
        colorSlot: 0,
        playedOn: '2026-11-18',
    },
    {
        roundId: BETHPAGE_RED_11_19,
        playerId: MANU,
        name: 'Manu',
        colorSlot: 1,
        playedOn: '2026-11-19',
    },
];

const candidates: VirtualMatchCandidate[] = [
    {
        roundId: BETHPAGE_RED_11_19,
        playerId: MANU,
        name: 'Manu',
        colorSlot: 1,
        playedOn: '2026-11-19',
        partners: ['Sean'],
        gross: 88,
    },
    {
        roundId: BETHPAGE_RED_11_19,
        playerId: SEAN,
        name: 'Sean',
        colorSlot: 2,
        playedOn: '2026-11-19',
        partners: ['Manu'],
        gross: 84,
    },
    {
        roundId: BETHPAGE_RED_11_18,
        playerId: GAB,
        name: 'Gab',
        colorSlot: 0,
        playedOn: '2026-11-18',
        partners: [],
        gross: 74,
    },
    {
        roundId: BETHPAGE_RED_11_02,
        playerId: GAB,
        name: 'Gab',
        colorSlot: 0,
        playedOn: '2026-11-02',
        partners: [],
        gross: 79,
    },
];

const VISIT_OPTIONS = {
    preserveScroll: true,
    preserveState: true,
    replace: true,
};

let get: MockInstance<typeof router.get>;

beforeEach(() => {
    get = vi.spyOn(router, 'get').mockImplementation(() => {});
});

afterEach(() => {
    vi.restoreAllMocks();
});

function renderPicks(chosen: VirtualMatchPick[] = picks) {
    render(
        <VirtualMatchPicks
            courseName="BETHPAGE BLACK"
            tees="Red"
            picks={chosen}
            candidates={candidates}
        />,
    );
}

async function openAddList() {
    await userEvent.click(screen.getByRole('button', { name: '+ Ajouter' }));

    return screen.findByRole('list', { name: 'Joueurs-rounds' });
}

describe('VirtualMatchPicks', () => {
    it('shows « Gab » and « 18 nov. » in a pill named « Retirer Gab, 18 nov. 2026 »', () => {
        renderPicks();

        const pill = screen.getByRole('button', {
            name: 'Retirer Gab, 18 nov. 2026',
        });

        expect(pill).toHaveTextContent(/^Gab18 nov\.$/);
        expect(
            screen
                .getAllByRole('button')
                .map(
                    (button) =>
                        button.getAttribute('aria-label') ?? button.textContent,
                ),
        ).toEqual([
            'Retirer Gab, 18 nov. 2026',
            'Retirer Manu, 19 nov. 2026',
            '+ Ajouter',
        ]);
    });

    it('removes Gab by his pill, through a visit without his pick', async () => {
        renderPicks();

        await userEvent.click(
            screen.getByRole('button', { name: 'Retirer Gab, 18 nov. 2026' }),
        );

        expect(get).toHaveBeenCalledTimes(1);
        expect(get).toHaveBeenCalledWith(
            `/rounds/compare?course=BETHPAGE+BLACK&tees=Red&picks=${BETHPAGE_RED_11_19}.${MANU}`,
            {},
            VISIT_OPTIONS,
        );
    });

    it('lists « Sean · 19 nov. 2026 · avec Manu » and greys the other round of Gab with « déjà choisi »', async () => {
        renderPicks();

        const list = await openAddList();
        const rows = within(list).getAllByRole('button');

        expect(rows.map((row) => row.textContent)).toEqual([
            'Manu · 19 nov. 2026 · avec Seandéjà choisi88',
            'Sean · 19 nov. 2026 · avec Manu84',
            'Gab · 18 nov. 2026 · seuldéjà choisi74',
            'Gab · 2 nov. 2026 · seuldéjà choisi79',
        ]);
        expect(rows.map((row) => (row as HTMLButtonElement).disabled)).toEqual([
            true,
            false,
            true,
            true,
        ]);

        await userEvent.click(rows[3]);

        expect(get).not.toHaveBeenCalled();
    });

    it('adds Sean at the end of the choice and closes the list', async () => {
        renderPicks();

        const list = await openAddList();

        await userEvent.click(
            within(list).getByRole('button', {
                name: /^Sean · 19 nov\. 2026 · avec Manu/,
            }),
        );

        expect(get).toHaveBeenCalledWith(
            `/rounds/compare?course=BETHPAGE+BLACK&tees=Red&picks=${BETHPAGE_RED_11_18}.${GAB}%2C${BETHPAGE_RED_11_19}.${MANU}%2C${BETHPAGE_RED_11_19}.${SEAN}`,
            {},
            VISIT_OPTIONS,
        );
        await waitFor(() =>
            expect(
                screen.queryByRole('list', { name: 'Joueurs-rounds' }),
            ).toBeNull(),
        );
    });

    it('closes the list without adding anything', async () => {
        renderPicks();

        await openAddList();
        await userEvent.keyboard('{Escape}');

        await waitFor(() =>
            expect(
                screen.queryByRole('list', { name: 'Joueurs-rounds' }),
            ).toBeNull(),
        );
        expect(get).not.toHaveBeenCalled();
    });
});
