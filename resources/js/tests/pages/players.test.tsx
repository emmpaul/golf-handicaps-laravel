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

import PlayersIndex from '@/pages/players/index';

// Spec players « Liste des joueurs », « Ajout d'un joueur », « Retrait d'un
// alias » and loading-feedback « Attentes des joueurs et des rounds ». The real
// <Form> of each inline form submits through the Inertia router; only the
// router's verbs are stubbed (jsdom cannot run a visit), and the visit
// callbacks they receive (onStart, onError) drive the form's own state.
// Head needs the head manager of createInertiaApp and renders nothing here;
// the orb paints on a canvas jsdom cannot draw.
// Mutations: label « Enregistrement du joueur » without « … » → the pending
// test goes red; `disabled={processing}` removed from the submit button → the
// pending test goes red; `aria-describedby` dropped → the error test goes red;
// « Aucun alias » replaced by « — » → the empty aliases test goes red; an
// alias label written with the straight apostrophe (« Retirer l'alias »,
// « Ajouter l'alias ») instead of the typographic one → the alias tests go red;
// « (provisoire) » dropped from the index → the index test goes red;
// ComputationHelp removed from the page → the explanation test goes red.
// Spec players « Index sur la page Joueurs »: Gab 10,0, Manu 14,5 provisional,
// Sean without round.

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    Head: () => null,
}));

vi.mock('thinking-orbs', () => ({
    ThinkingOrb: () => <canvas data-testid="orb" />,
}));

const GAB = '0190a6b2-7c3e-7000-8000-000000000001';
const MANU = '0190a6b2-7c3e-7000-8000-000000000002';
const MANU_ALIAS = '0190a6b2-7c3e-7000-8000-000000000003';
const EMMA_ALIAS = '0190a6b2-7c3e-7000-8000-000000000004';

const SEAN = '0190a6b2-7c3e-7000-8000-000000000005';

const players = [
    { id: GAB, name: 'gab', aliases: [], indexT: 100, provisional: false },
    {
        id: MANU,
        name: 'Manu',
        aliases: [
            { id: EMMA_ALIAS, alias: 'EMMA' },
            { id: MANU_ALIAS, alias: 'MANU' },
        ],
        indexT: 145,
        provisional: true,
    },
    { id: SEAN, name: 'Sean', aliases: [], indexT: null, provisional: false },
];

type VisitOptions = {
    onStart?: (visit: unknown) => void;
    onError?: (errors: Record<string, string>) => void;
};

let post: MockInstance<typeof router.post>;
let remove: MockInstance<typeof router.delete>;

beforeEach(() => {
    post = vi.spyOn(router, 'post').mockImplementation(() => {});
    vi.spyOn(router, 'patch').mockImplementation(() => {});
    remove = vi.spyOn(router, 'delete').mockImplementation(() => {});
});

afterEach(() => {
    vi.restoreAllMocks();
});

/** The add form, as its labelled section shows it. */
function addSection() {
    return screen.getByRole('region', { name: 'Ajouter un joueur' });
}

describe('Players page', () => {
    it('lists the players in the order received, each with its aliases', () => {
        render(<PlayersIndex players={players} />);

        const list = screen.getByRole('list', { name: 'Joueurs' });
        const names = within(list)
            .getAllByRole('heading', { level: 2 })
            .map((heading) => heading.textContent);

        expect(names).toEqual(['gab', 'Manu', 'Sean']);
        expect(
            within(screen.getByRole('list', { name: 'Alias de Manu' }))
                .getAllByRole('listitem')
                .map(
                    (item) =>
                        item.querySelector('[data-slot="alias"]')?.textContent,
                ),
        ).toEqual(['EMMA', 'MANU']);
    });

    it('shows « Index 10,0 », « Index 14,5 (provisoire) » and « Index — »', () => {
        render(<PlayersIndex players={players} />);

        expect(
            screen
                .getAllByTestId('player-index')
                .map((index) => index.textContent),
        ).toEqual(['Index 10,0', 'Index 14,5 (provisoire)', 'Index —']);
    });

    it('offers « Comment l’index est calculé », closed', () => {
        render(<PlayersIndex players={players} />);

        const title = screen.getByText('Comment l’index est calculé');

        expect(title.tagName).toBe('SUMMARY');
        expect((title.closest('details') as HTMLDetailsElement).open).toBe(
            false,
        );
    });

    it('shows « Aucun alias » for a player without alias', () => {
        render(<PlayersIndex players={players} />);

        const gabRow = screen
            .getByRole('heading', { level: 2, name: 'gab' })
            .closest('li') as HTMLElement;

        expect(within(gabRow).getByText('Aucun alias').textContent).toBe(
            'Aucun alias',
        );
        expect(screen.queryByRole('list', { name: 'Alias de gab' })).toBeNull();
    });

    it('offers « Ajouter l’alias » to each player', () => {
        render(<PlayersIndex players={players} />);

        const gabRow = screen
            .getByRole('heading', { level: 2, name: 'gab' })
            .closest('li') as HTMLElement;

        expect(
            within(gabRow).getByRole('button', { name: 'Ajouter l’alias' })
                .textContent,
        ).toBe('Ajouter l’alias');
    });

    it('removes an alias at once, without confirmation', async () => {
        render(<PlayersIndex players={players} />);

        await userEvent.click(
            screen.getByRole('button', {
                name: 'Retirer l’alias MANU de Manu',
            }),
        );

        expect(screen.queryByRole('alertdialog')).toBeNull();
        expect(remove).toHaveBeenCalledTimes(1);
        expect(remove.mock.calls[0][0]).toBe(
            `/players/${MANU}/aliases/${MANU_ALIAS}`,
        );
    });

    it('posts the new player to /players', async () => {
        render(<PlayersIndex players={players} />);

        await userEvent.type(
            within(addSection()).getByLabelText('Nouveau joueur'),
            'Alex',
        );
        await userEvent.click(
            within(addSection()).getByRole('button', { name: 'Ajouter' }),
        );

        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0][0]).toBe('/players');
        expect(post.mock.calls[0][1]).toEqual({
            name: 'Alex',
        });
    });

    it('shows the field error in an alert tied to the invalid field', async () => {
        post.mockImplementation((_url, _data, options) => {
            (options as VisitOptions).onError?.({
                name: 'Ce joueur existe déjà',
            });
        });

        render(<PlayersIndex players={players} />);

        const field = within(addSection()).getByLabelText('Nouveau joueur');

        await userEvent.type(field, 'gab');
        await userEvent.click(
            within(addSection()).getByRole('button', { name: 'Ajouter' }),
        );

        const alert = within(addSection()).getByRole('alert');

        expect(alert.textContent).toBe('Ce joueur existe déjà');
        expect(alert.id).not.toBe('');
        expect(field.getAttribute('aria-invalid')).toBe('true');
        expect(field.getAttribute('aria-describedby')).toBe(alert.id);
        expect(screen.getAllByRole('alert')).toHaveLength(1);
    });

    it('shows « Enregistrement du joueur… » and disables « Ajouter » while sending', async () => {
        post.mockImplementation((_url, _data, options) => {
            (options as VisitOptions).onStart?.({});
        });

        render(<PlayersIndex players={players} />);

        await userEvent.type(
            within(addSection()).getByLabelText('Nouveau joueur'),
            'Alex',
        );
        await act(async () => {
            await userEvent.click(
                within(addSection()).getByRole('button', { name: 'Ajouter' }),
            );
        });

        expect(within(addSection()).getByRole('status').textContent).toBe(
            'Enregistrement du joueur…',
        );
        expect(
            within(addSection()).getByRole('button', { name: 'Ajouter' }),
        ).toBeDisabled();
    });

    it('keeps every status region empty at rest', () => {
        render(<PlayersIndex players={players} />);

        for (const status of screen.getAllByRole('status')) {
            expect(status).toBeEmptyDOMElement();
        }
    });
});
