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

// Spec players « Liste des joueurs », « Index sur la page Joueurs », « Ajout
// d'un joueur », « Retrait d'un alias », « Mise en page de la page Joueurs »,
// « Modification repliée d'un joueur » and loading-feedback « Attentes des
// joueurs et des rounds ». The real <Form> of each inline form submits through
// the Inertia router; only the router's verbs are stubbed (jsdom cannot run a
// visit), and the visit callbacks they receive (onStart, onError) drive the
// form's own state. Head needs the head manager of createInertiaApp and
// renders nothing here; usePage is simulated for the top row; the orb paints
// on a canvas jsdom cannot draw.
// Mutations: label « Enregistrement du joueur » without « … » → the pending
// test goes red; `disabled={processing}` removed from the submit button → the
// pending test goes red; `aria-describedby` dropped → the error test goes red;
// « Aucun alias » replaced by « — » → the empty aliases test goes red; an
// alias label written with the straight apostrophe (« Retirer l'alias »,
// « Ajouter l'alias ») instead of the typographic one → the alias tests go red;
// the state « INDEX » written for a provisional index (`provisional` ignored)
// → the index test reads « INDEX » for Manu and goes red; each line open on
// its own (one `open` state per line instead of the page's) → the « one line
// open » test finds Sean still open and goes red; the line closed on an error
// (`setOpen(null)` on submit) → the error test goes red; the swatch at the
// slot + 1 → the line test goes red; ComputationLink removed from the page →
// the link test goes red.
// Spec players « Index sur la page Joueurs »: Gab 10,0, Manu 14,5 provisional,
// Sean without round.

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ url: '/players', props: {} }),
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
    {
        id: GAB,
        name: 'gab',
        aliases: [],
        indexT: 100,
        provisional: false,
        colorSlot: 0,
    },
    {
        id: MANU,
        name: 'Manu',
        aliases: [
            { id: EMMA_ALIAS, alias: 'EMMA' },
            { id: MANU_ALIAS, alias: 'MANU' },
        ],
        indexT: 145,
        provisional: true,
        colorSlot: 1,
    },
    {
        id: SEAN,
        name: 'Sean',
        aliases: [],
        indexT: null,
        provisional: false,
        colorSlot: 2,
    },
];

type VisitOptions = {
    onStart?: (visit: unknown) => void;
    onError?: (errors: Record<string, string>) => void;
};

let post: MockInstance<typeof router.post>;
let remove: MockInstance<typeof router.delete>;
let patch: MockInstance<typeof router.patch>;

beforeEach(() => {
    post = vi.spyOn(router, 'post').mockImplementation(() => {});
    patch = vi.spyOn(router, 'patch').mockImplementation(() => {});
    remove = vi.spyOn(router, 'delete').mockImplementation(() => {});
});

afterEach(() => {
    vi.restoreAllMocks();
});

/** The add form, as its labelled section shows it. */
function addSection() {
    return screen.getByRole('region', { name: 'Ajouter un joueur' });
}

/** The line of the player named `name`. */
const row = (name: string) =>
    screen
        .getByRole('heading', { level: 2, name })
        .closest('li') as HTMLElement;

/** Opens the line of `name` by its « Modifier ». */
const openRow = (name: string) =>
    userEvent.click(
        within(row(name)).getByRole('button', { name: 'Modifier' }),
    );

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

    it("shows the index over its state: « 10,0 » « INDEX », « 14,5 » « PROVISOIRE », « — » « PAS D'INDEX »", () => {
        render(<PlayersIndex players={players} />);

        expect(
            screen
                .getAllByTestId('player-index')
                .map((index) => index.textContent),
        ).toEqual(['10,0INDEX', '14,5PROVISOIRE', "—PAS D'INDEX"]);
    });

    // Spec « Mise en page de la page Joueurs ».
    it('draws each line with its 10 px swatch, its name at 20 px and its index at 22 px', () => {
        render(<PlayersIndex players={players} />);

        const manu = row('Manu');
        const swatch = manu.querySelector(
            '[aria-hidden="true"]',
        ) as HTMLElement;

        expect(swatch.className).toContain('bg-player-2');
        expect(swatch.className).toContain('size-2.5');
        expect(
            within(manu).getByRole('heading', { level: 2 }).className,
        ).toContain('text-name-m');
        expect(within(manu).getByText('14,5').className).toContain(
            'text-num-l',
        );
        expect(within(manu).getByText('PROVISOIRE').className).toContain(
            'text-caption',
        );
    });

    it('shows « ALIAS » then the alias pills of 32 px, each with its remove button', () => {
        render(<PlayersIndex players={players} />);

        const manu = row('Manu');
        const pill = within(manu)
            .getByText('MANU')
            .closest('li') as HTMLElement;

        expect(within(manu).getByText('Alias').className).toContain(
            'uppercase',
        );
        expect(pill.className).toContain('h-8');
        expect(pill.className).toContain('bg-surface-2');
        expect(
            within(pill).getByRole('button', {
                name: 'Retirer l’alias MANU de Manu',
            }),
        ).toBeInTheDocument();
    });

    // Spec « Modification repliée d'un joueur ».
    it('folds the rename and the new alias behind « Modifier », which becomes « Fermer »', async () => {
        render(<PlayersIndex players={players} />);

        expect(screen.queryByLabelText('Nouvel alias de Sean')).toBeNull();
        expect(screen.queryByRole('button', { name: 'Renommer' })).toBeNull();

        await openRow('Sean');

        const sean = row('Sean');
        expect(
            (within(sean).getByLabelText('Nom de Sean') as HTMLInputElement)
                .value,
        ).toBe('Sean');
        expect(
            within(sean).getByRole('button', { name: 'Renommer' }),
        ).toBeInTheDocument();
        expect(
            within(sean).getByLabelText('Nouvel alias de Sean'),
        ).toBeInTheDocument();
        expect(
            within(sean).getByRole('button', { name: 'Ajouter l’alias' })
                .textContent,
        ).toBe('Ajouter l’alias');
        expect(
            within(sean).getByText(
                'Les alias servent à reconnaître le joueur sur les photos de carte.',
            ),
        ).toBeInTheDocument();
        expect(
            within(sean)
                .getByRole('button', { name: 'Fermer' })
                .getAttribute('aria-expanded'),
        ).toBe('true');

        await userEvent.click(
            within(sean).getByRole('button', { name: 'Fermer' }),
        );

        expect(screen.queryByLabelText('Nouvel alias de Sean')).toBeNull();
    });

    it('keeps one player open at a time', async () => {
        render(<PlayersIndex players={players} />);

        await openRow('Sean');
        await openRow('gab');

        expect(
            screen.getByLabelText('Nouvel alias de gab'),
        ).toBeInTheDocument();
        expect(screen.queryByLabelText('Nouvel alias de Sean')).toBeNull();
        expect(
            within(row('Sean')).getByRole('button', { name: 'Modifier' }),
        ).toBeInTheDocument();
    });

    it('keeps the line open with « Ce joueur existe déjà » under the name when the rename is refused', async () => {
        patch.mockImplementation((_url, _data, options) => {
            (options as VisitOptions).onError?.({
                name: 'Ce joueur existe déjà',
            });
        });

        render(<PlayersIndex players={players} />);
        await openRow('Manu');

        const name = within(row('Manu')).getByLabelText('Nom de Manu');
        await userEvent.clear(name);
        await userEvent.type(name, 'GAB');
        await userEvent.click(
            within(row('Manu')).getByRole('button', { name: 'Renommer' }),
        );

        expect(within(row('Manu')).getByRole('alert').textContent).toBe(
            'Ce joueur existe déjà',
        );
        expect(
            within(row('Manu')).getByRole('button', { name: 'Fermer' }),
        ).toBeInTheDocument();
    });

    // Spec handicap-index « Lien vers le calcul de l'index »: a link to the
    // computation page, none of the rule lines on the page. Mutations: the
    // former folded block kept, or the link sent to /handicaps → red.
    it('links « Comment l’index est calculé » to /handicaps/calcul, without the rule lines', () => {
        render(<PlayersIndex players={players} />);

        expect(
            screen
                .getByRole('link', { name: 'Comment l’index est calculé' })
                .getAttribute('href'),
        ).toBe('/handicaps/calcul');
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
    });

    it('shows « Aucun alias » for a player without alias', () => {
        render(<PlayersIndex players={players} />);

        const gabRow = row('gab');

        expect(within(gabRow).getByText('Alias')).toBeInTheDocument();
        expect(within(gabRow).getByText('Aucun alias').textContent).toBe(
            'Aucun alias',
        );
        expect(screen.queryByRole('list', { name: 'Alias de gab' })).toBeNull();
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

        expect(
            within(addSection()).getByRole('button', { name: 'Ajouter' })
                .className,
        ).toContain('bg-ink');
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
