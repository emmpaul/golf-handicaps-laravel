import { router } from '@inertiajs/react';
import type { VisitHelperOptions } from '@inertiajs/core';
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

import { store } from '@/actions/App/Http/Controllers/RoundController';
import {
    cardEditorState,
    emptyEditorState,
} from '@/components/round-editor/form-state';
import type {
    EditorState,
    KnownCourse,
    PlayerOption,
} from '@/components/round-editor/form-state';
import { RoundEditor } from '@/components/round-editor/round-editor';
import {
    DISTANCES,
    GAB,
    MANU,
    PARS,
    PLAYERS,
    REFERENCE_CARD,
    ROUND,
    SEAN,
    STROKE_INDEXES,
} from '@/tests/reference-round';
import { holeCells } from '@/tests/grid-cells';

// Spec rounds « Saisie manuelle neuve sans alerte », « Contrôles de
// cohérence », « Totaux calculés », « Pré-remplissage depuis un parcours
// connu », « Lignes de joueur », « Lignes de joueur groupées sur téléphone »,
// « Grille de saisie sur téléphone », « Mise en page de l'éditeur »,
// « Détection de doublon », « Marques sur le champ de date » and
// loading-feedback « Attentes de l'éditeur de round ». The router's verbs are
// stubbed (jsdom cannot run a visit); the visit callbacks they receive are
// played by hand, in the order Inertia plays them. jsdom has no matchMedia
// (read by the orb for reduced motion); the orb paints on a canvas jsdom
// cannot draw.
// Mutations of the layout (task 6.1):
// - the « RET. » row dropped → the totals rows test goes red;
// - an untouched empty score cell painted amber (the empty-cell classes keyed
//   on the problem instead of the cell shown in error) → the fresh entry cell
//   test goes red;
// - the read name put on its own row above the line → the read name test
//   goes red;
// - the distance problems dropped while the « Distances » block is closed →
//   the closed block test goes red;
// - the name of a line in the grid head painted `text-ink` instead of its
//   player's colour → the grid head test goes red.
// Mutations:
// - the created player matched with its case (`player.name === name`) →
//   « julien » finds no « Julien » and the association test goes red;
// - `force` dropped from the data sent → the « Enregistrer quand même » test
//   reads `force: false` and goes red;
// - `saving` reset by every onFinish → the pending test goes red after the
//   visit that opens the list;
// - every empty cell flagged from the start (`flagUntouchedEmpty` ignored) →
//   the fresh entry and left-cell tests go red;
// - the totals of the grid computed over holes 1 to 8 → OUT reads 33 and the
//   par TOT 67;
// - « Ajouter une ligne » shown at 4 lines, « Retirer la ligne 1 » at 1 line →
//   the two line count tests go red;
// - « Création du joueur » without « … » → the pending creation test goes red;
// - the known course resetting the lines → the course test loses the « 5 »;
// - the added line put first (`[newLine(), ...state.lines]`) → line 3 is
//   Manu's, the forgotten line test reads 3 lines in another order and goes red.

vi.mock('thinking-orbs', () => ({
    ThinkingOrb: () => <canvas data-testid="orb" />,
}));

const JULIEN = {
    id: '0190a6b2-7c3e-7000-8000-000000000009',
    name: 'Julien',
    colorSlot: 3,
};

const course: KnownCourse = {
    courseName: 'Bethpage Black',
    tees: 'Red',
    pars: PARS,
    strokeIndexes: STROKE_INDEXES,
    distances: DISTANCES,
};

let post: MockInstance<typeof router.post>;
let put: MockInstance<typeof router.put>;

beforeEach(() => {
    vi.stubGlobal('matchMedia', () => ({
        matches: false,
        addEventListener: () => {},
        removeEventListener: () => {},
    }));
    window.history.replaceState({}, '', '/rounds/new');
    post = vi.spyOn(router, 'post').mockImplementation(() => {});
    put = vi.spyOn(router, 'put').mockImplementation(() => {});
});

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

function editor({
    initial = emptyEditorState('2026-10-17'),
    players = PLAYERS,
    courses = [],
}: {
    initial?: EditorState;
    players?: PlayerOption[];
    courses?: KnownCourse[];
} = {}) {
    return (
        <RoundEditor
            initial={initial}
            players={players}
            courses={courses}
            action={store()}
        />
    );
}

const cell = (name: string) =>
    screen.getByRole('textbox', { name }) as HTMLInputElement;

const saveButton = () =>
    screen.getByRole('button', {
        name: /^(Enregistrer|\d+ problèmes? à corriger)$/,
    });

/** The messages of the save bar, joined there by « · ». */
const problemMessages = () =>
    (screen.queryByTestId('problem-messages')?.textContent ?? '')
        .split(' · ')
        .filter(Boolean);

/** Opens the « Distances » block under the grid. */
async function openDistances() {
    await userEvent.click(screen.getByText('Distances'));
}

/** The options of the n-th call of a stubbed router verb. */
const visitOptions = (verb: MockInstance<typeof router.post>, call = 0) =>
    verb.mock.calls[call][2] as VisitHelperOptions;

/** Plays the callbacks of a finished visit, as Inertia does, and lets the awaiting form settle. */
async function finish(
    options: VisitHelperOptions,
    {
        url,
        flash = {},
        props = {},
    }: { url: string; flash?: object; props?: object },
) {
    await act(async () => {
        options.onFlash?.(flash as never);
        options.onSuccess?.({ url, props } as never);
        options.onFinish?.({} as never);
    });
}

async function startCreatingPlayer(name: string) {
    await userEvent.selectOptions(
        screen.getByRole('combobox', { name: 'Joueur de la ligne 1' }),
        'Nouveau joueur…',
    );
    await userEvent.type(
        screen.getByRole('textbox', {
            name: 'Nom du nouveau joueur de la ligne 1',
        }),
        name,
    );
    await userEvent.click(
        screen.getByRole('button', { name: 'Créer le joueur de la ligne 1' }),
    );
}

describe('RoundEditor, fresh manual entry', () => {
    it('marks no cell in error and counts every problem on the button', () => {
        const { container } = render(editor());

        expect(
            container.querySelectorAll('[aria-invalid="true"]'),
        ).toHaveLength(0);
        // Course and tees, 18 pars, 18 indexes, 18 scores, the line without a player.
        expect(saveButton().textContent).toBe('57 problèmes à corriger');
        expect(saveButton()).toBeDisabled();
        expect(screen.getByRole('button', { name: 'Date' }).textContent).toBe(
            '17 octobre 2026',
        );
    });

    it('marks the par of hole 1 left empty, and no other untouched cell', async () => {
        render(editor());

        await userEvent.click(cell('Par trou 1'));
        await userEvent.tab();

        expect(cell('Par trou 1').getAttribute('aria-invalid')).toBe('true');
        expect(cell('Par trou 2').getAttribute('aria-invalid')).toBeNull();
        expect(cell('Index trou 1').getAttribute('aria-invalid')).toBeNull();
        expect(saveButton().textContent).toBe('57 problèmes à corriger');
    });

    it('recomputes the problems at each keystroke: 16 then 5', async () => {
        render(editor());

        await userEvent.type(cell('Ligne 1 trou 2'), '16');

        expect(problemMessages()).toContain('Score 16 hors de 1 à 15');
        expect(cell('Ligne 1 trou 2').getAttribute('aria-invalid')).toBe(
            'true',
        );

        await userEvent.clear(cell('Ligne 1 trou 2'));
        await userEvent.type(cell('Ligne 1 trou 2'), '5');

        expect(problemMessages()).not.toContain('Score 16 hors de 1 à 15');
        expect(cell('Ligne 1 trou 2').getAttribute('aria-invalid')).toBeNull();
    });

    it('computes OUT from the holes, empty while a hole is empty', async () => {
        render(editor());

        const front = [5, 5, 3, 5, 4, 4, 4, 3];

        const holes = holeCells('Ligne 1');

        for (const [hole, strokes] of front.entries()) {
            await userEvent.type(holes[hole], `${strokes}`);
        }

        expect(cell('Ligne 1 OUT').value).toBe('');

        await userEvent.type(cell('Ligne 1 trou 9'), '5');

        expect(cell('Ligne 1 OUT').value).toBe('38');
        expect(cell('Ligne 1 OUT').readOnly).toBe(true);
        expect(cell('Ligne 1 TOT').value).toBe('');
    });
});

describe('RoundEditor, known course', () => {
    it('fills pars, indexes and distances without touching the date nor the lines', async () => {
        render(editor({ courses: [course] }));

        await userEvent.type(cell('Ligne 1 trou 1'), '5');
        await userEvent.selectOptions(
            screen.getByRole('combobox', { name: 'Parcours connu' }),
            'Bethpage Black / Red',
        );
        await openDistances();

        const row = (label: string) =>
            holeCells(label).map((input) => input.value);

        expect(row('Par')).toEqual([
            '4',
            '4',
            '3',
            '5',
            '4',
            '4',
            '5',
            '3',
            '4',
            '4',
            '4',
            '4',
            '5',
            '3',
            '4',
            '4',
            '3',
            '4',
        ]);
        expect(row('Index')).toEqual([
            '8',
            '16',
            '18',
            '2',
            '4',
            '10',
            '6',
            '14',
            '12',
            '9',
            '11',
            '7',
            '3',
            '17',
            '1',
            '5',
            '13',
            '15',
        ]);
        expect(row('Distance')).toEqual([
            '420',
            '344',
            '128',
            '444',
            '398',
            '377',
            '480',
            '155',
            '392',
            '353',
            '405',
            '392',
            '470',
            '149',
            '401',
            '423',
            '173',
            '337',
        ]);
        expect(cell('Par TOT').value).toBe('71');
        expect(cell('Parcours').value).toBe('Bethpage Black');
        expect(cell('Tees').value).toBe('Red');
        expect(screen.getByRole('button', { name: 'Date' }).textContent).toBe(
            '17 octobre 2026',
        );
        expect(cell('Ligne 1 trou 1').value).toBe('5');
        expect(
            screen.getAllByRole('group', { name: /^Ligne \d$/ }),
        ).toHaveLength(1);
    });
});

describe('RoundEditor, lines of players', () => {
    it('offers no « Ajouter une ligne » at 4 lines', async () => {
        render(editor());

        for (const _ of [2, 3, 4]) {
            await userEvent.click(
                screen.getByRole('button', { name: 'Ajouter une ligne' }),
            );
        }

        expect(
            screen.getAllByRole('group', { name: /^Ligne \d$/ }),
        ).toHaveLength(4);
        expect(
            screen.queryByRole('button', { name: 'Ajouter une ligne' }),
        ).toBeNull();
    });

    it('adds a forgotten third line, associated to Sean with its 18 scores', async () => {
        const seanStrokes = [
            4, 5, 3, 6, 5, 4, 6, 3, 4, 5, 4, 5, 5, 3, 4, 5, 3, 4,
        ];

        render(editor({ initial: cardEditorState(REFERENCE_CARD) }));

        await userEvent.click(
            screen.getByRole('button', { name: 'Ajouter une ligne' }),
        );
        await userEvent.selectOptions(
            screen.getByRole('combobox', { name: 'Joueur de la ligne 3' }),
            'Sean',
        );
        const holes = holeCells('Ligne 3');

        for (const [hole, strokes] of seanStrokes.entries()) {
            await userEvent.type(holes[hole], String(strokes));
        }

        expect(
            screen.getAllByRole('group', { name: /^Ligne \d$/ }),
        ).toHaveLength(3);
        expect(
            screen
                .getAllByRole('combobox', { name: /^Joueur de la ligne \d$/ })
                .map((select) => (select as HTMLSelectElement).value),
        ).toEqual([GAB, MANU, SEAN]);

        await userEvent.click(saveButton());

        expect(post.mock.calls[0][1]).toEqual({
            ...REFERENCE_CARD,
            lines: [
                ...REFERENCE_CARD.lines,
                {
                    playerId: SEAN,
                    strokes: [
                        4, 5, 3, 6, 5, 4, 6, 3, 4, 5, 4, 5, 5, 3, 4, 5, 3, 4,
                    ],
                },
            ],
            force: false,
        });
    });

    it('offers no « Retirer la ligne 1 » at 1 line', async () => {
        render(editor());

        expect(
            screen.queryByRole('button', { name: 'Retirer la ligne 1' }),
        ).toBeNull();

        await userEvent.click(
            screen.getByRole('button', { name: 'Ajouter une ligne' }),
        );

        expect(
            screen
                .getAllByRole('button', { name: /^Retirer la ligne \d$/ })
                .map((button) => button.getAttribute('aria-label')),
        ).toEqual(['Retirer la ligne 1', 'Retirer la ligne 2']);
    });

    it('associates the player created as « julien » once the reloaded props hold « Julien »', async () => {
        const view = render(editor());

        await userEvent.type(cell('Ligne 1 trou 1'), '5');
        await startCreatingPlayer('julien');

        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0][0]).toEqual({
            url: '/players',
            method: 'post',
        });
        expect(post.mock.calls[0][1]).toEqual({ name: 'julien' });
        expect(visitOptions(post)).toMatchObject({
            preserveState: true,
            only: ['players'],
            errorBag: 'linePlayer',
        });

        const reloaded = [...PLAYERS, JULIEN];

        view.rerender(editor({ players: reloaded }));
        await finish(visitOptions(post), {
            url: '/rounds/new',
            props: { players: reloaded },
        });

        const select = screen.getByRole('combobox', {
            name: 'Joueur de la ligne 1',
        }) as HTMLSelectElement;

        expect(select.value).toBe(JULIEN.id);
        expect(select.selectedOptions[0].textContent).toBe('Julien');
        expect(cell('Ligne 1 trou 1').value).toBe('5');
        expect(
            screen.queryByRole('textbox', {
                name: 'Nom du nouveau joueur de la ligne 1',
            }),
        ).toBeNull();
    });

    it('shows « Création du joueur… » beside the line while creating', async () => {
        render(editor());

        await startCreatingPlayer('Julien');

        const line = screen.getByRole('group', { name: 'Ligne 1' });

        expect(
            within(line)
                .getAllByRole('status')
                .map((status) => status.textContent),
        ).toEqual(['Création du joueur…']);
        expect(
            within(line).getByRole('button', {
                name: 'Créer le joueur de la ligne 1',
            }),
        ).toBeDisabled();
    });

    it('shows « Ce joueur existe déjà » beside the line, without losing a cell', async () => {
        render(editor());

        await userEvent.type(cell('Ligne 1 trou 1'), '5');
        await startCreatingPlayer('gab');

        await act(async () => {
            visitOptions(post).onError?.({ name: 'Ce joueur existe déjà' });
            visitOptions(post).onFinish?.({} as never);
        });

        const line = screen.getByRole('group', { name: 'Ligne 1' });

        expect(await within(line).findByRole('alert')).toHaveTextContent(
            /^Ce joueur existe déjà$/,
        );
        expect(cell('Ligne 1 trou 1').value).toBe('5');
        expect(
            (
                screen.getByRole('combobox', {
                    name: 'Joueur de la ligne 1',
                }) as HTMLSelectElement
            ).value,
        ).toBe('new');
    });
});

describe('RoundEditor, saving', () => {
    it('warns of a duplicate with a link, then sends the same card with `force: true`', async () => {
        render(editor({ initial: cardEditorState(REFERENCE_CARD) }));

        await userEvent.click(saveButton());

        expect(post.mock.calls[0][0]).toEqual({
            url: '/rounds',
            method: 'post',
        });
        expect(post.mock.calls[0][1]).toEqual({
            ...REFERENCE_CARD,
            force: false,
        });

        act(() => visitOptions(post).onStart?.({} as never));
        await finish(visitOptions(post), {
            url: '/rounds/new',
            flash: { duplicate: { roundId: ROUND } },
        });

        const alert = screen
            .getByText('Ce round semble déjà enregistré')
            .closest('[data-slot="alert"]') as HTMLElement;

        expect(
            within(alert)
                .getByRole('link', { name: 'Voir le round existant' })
                .getAttribute('href'),
        ).toBe(`/rounds/${ROUND}`);
        expect(saveButton()).toBeEnabled();

        await userEvent.click(
            within(alert).getByRole('button', {
                name: 'Enregistrer quand même',
            }),
        );

        expect(post).toHaveBeenCalledTimes(2);
        expect(post.mock.calls[1][1]).toEqual({
            ...REFERENCE_CARD,
            force: true,
        });
        expect(put).not.toHaveBeenCalled();
    });

    it('shows « Enregistrement du round… » and keeps the button disabled until the next page', async () => {
        render(editor({ initial: cardEditorState(REFERENCE_CARD) }));

        await userEvent.click(saveButton());
        act(() => visitOptions(post).onStart?.({} as never));

        const pending = () =>
            screen
                .getAllByRole('status')
                .map((status) => status.textContent)
                .filter((text) => text !== '');

        expect(pending()).toEqual(['Enregistrement du round…']);
        expect(saveButton()).toBeDisabled();

        await finish(visitOptions(post), { url: '/rounds' });

        expect(pending()).toEqual(['Enregistrement du round…']);
        expect(saveButton()).toBeDisabled();
    });
});

describe('RoundEditor, date field', () => {
    it('marks an empty date once left, announced in error and counted', async () => {
        render(
            editor({
                initial: cardEditorState({ ...REFERENCE_CARD, playedOn: null }),
            }),
        );

        const date = screen.getByRole('button', { name: 'Date' });

        expect(date.getAttribute('aria-invalid')).toBeNull();
        expect(saveButton().textContent).toBe('1 problème à corriger');

        act(() => date.focus());
        await userEvent.tab();

        expect(date.getAttribute('aria-invalid')).toBe('true');
        expect(saveButton().textContent).toBe('1 problème à corriger');
    });
});

// Spec scorecard-import « Repère de lecture par l'IA », « Nom lu sur chaque
// ligne », « Contrôle des totaux lus » and design D7: the card of an import
// carries the names and totals read; the editor of an import gets its
// `importContext`. The read card: Gab's hole 9 read as 3 with OUT 38.
// Mutations:
// - the totals of a read line recomputed at each keystroke (`totals` of the
//   grid row from `withTotals` for every line) → the read OUT test reads 36
//   and goes red;
// - the totals of a read line kept read-only → the editable totals test goes
//   red;
// - « lu « … » » shown for every line (`line.readName ?? ''`) → the added
//   line test finds « lu « » » and goes red;
// - the mark shown without `readByAi` → the failed import test goes red;
// - the straight apostrophe in « l'IA » → the mark test goes red;
// - the mark painted `text-ink-2` instead of `text-info` → the mark test goes
//   red.

const READ_GAB = [5, 5, 3, 5, 4, 4, 4, 3, 3, 4, 4, 4, 4, 3, 4, 5, 3, 3];

const readCard = (): EditorState =>
    cardEditorState({
        courseName: 'BETHPAGE BLACK',
        tees: 'Red',
        playedOn: '2026-10-17',
        holes: REFERENCE_CARD.holes,
        lines: [
            {
                playerId: null,
                strokes: READ_GAB,
                readName: 'GAB',
                totals: { out: 38, in: 34, total: 72 },
            },
            {
                playerId: null,
                strokes: REFERENCE_CARD.lines[1].strokes,
                readName: 'MANU',
                totals: { out: 46, in: 42, total: 88 },
            },
        ],
        parTotals: { out: 36, in: 35, total: 71 },
    });

const IMPORT = '0190a6b2-7c3e-7000-8000-0000000000c1';

function importEditor(readByAi = true) {
    return (
        <RoundEditor
            initial={readCard()}
            players={PLAYERS}
            courses={[]}
            action={store()}
            importContext={{
                importId: IMPORT,
                photoUrl: `/imports/${IMPORT}/photo`,
                readByAi,
                failure: null,
            }}
        />
    );
}

describe('RoundEditor, card of an import', () => {
    it('shows « Carte lue par l’IA, vérifie chaque case » under the photo, in `info`', () => {
        render(importEditor());

        const mark = screen.getByText(
            'Carte lue par l’IA, vérifie chaque case',
        );

        expect(mark).toHaveClass('text-info');
        expect(
            screen
                .getByRole('img', { name: 'Photo de la carte importée' })
                .compareDocumentPosition(mark) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
        expect(
            mark.compareDocumentPosition(cell('Parcours')) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
    });

    it('shows no mark when the AI gave no draft', () => {
        render(importEditor(false));

        expect(screen.queryByText(/Carte lue par l/)).toBeNull();
    });

    it('shows no mark and no saving status without an import (manual entry, saved round)', () => {
        render(editor({ initial: readCard() }));

        expect(screen.queryByText(/Carte lue par l/)).toBeNull();
        expect(screen.queryByTestId('autosave-status')).toBeNull();
        expect(
            screen.queryByRole('img', { name: 'Photo de la carte importée' }),
        ).toBeNull();
    });

    it('shows « L1 », the player list, « lu « GAB » » and the remove button on one row', () => {
        render(importEditor());

        const group = screen.getByRole('group', { name: 'Ligne 1' });
        const number = within(group).getByText('L1');
        // The list sits in the wrapper that draws its chevron.
        const select = within(group)
            .getByRole('combobox', { name: 'Joueur de la ligne 1' })
            .closest('[data-slot="native-select-wrapper"]');
        const read = within(group).getByText('lu « GAB »');
        const remove = within(group).getByRole('button', {
            name: 'Retirer la ligne 1',
        });

        expect([...(number.parentElement?.children ?? [])]).toEqual([
            number,
            select,
            read,
            remove,
        ]);
        expect(
            within(screen.getByRole('group', { name: 'Ligne 2' })).getByText(
                'lu « MANU »',
            ),
        ).toBeInTheDocument();
    });

    it('shows no read name on an added line', async () => {
        render(importEditor());

        await userEvent.click(
            screen.getByRole('button', { name: 'Ajouter une ligne' }),
        );

        expect(
            within(screen.getByRole('group', { name: 'Ligne 3' })).queryByText(
                /^lu «/,
            ),
        ).toBeNull();
    });

    it('keeps the totals read on a line, editable, and computes those of an added line', async () => {
        render(importEditor());

        expect(cell('Ligne 1 OUT').value).toBe('38');
        expect(cell('Ligne 1 OUT').readOnly).toBe(false);
        expect(cell('Par TOT').value).toBe('71');
        expect(cell('Par TOT').readOnly).toBe(false);

        await userEvent.clear(cell('Ligne 1 trou 1'));
        await userEvent.type(cell('Ligne 1 trou 1'), '4');

        expect(cell('Ligne 1 OUT').value).toBe('38');

        await userEvent.clear(cell('Ligne 1 TOT'));
        await userEvent.type(cell('Ligne 1 TOT'), '71');

        expect(cell('Ligne 1 TOT').value).toBe('71');

        await userEvent.click(
            screen.getByRole('button', { name: 'Ajouter une ligne' }),
        );
        await userEvent.type(cell('Ligne 3 trou 1'), '5');

        expect(cell('Ligne 3 OUT').readOnly).toBe(true);
        expect(cell('Ligne 3 OUT').value).toBe('');
    });

    it('flags « Somme aller 36 ≠ OUT 38 » until the 3 of hole 9 becomes 5', async () => {
        render(importEditor());

        expect(problemMessages()).toContain('Somme aller 36 ≠ OUT 38');
        expect(cell('Ligne 1 OUT').getAttribute('aria-invalid')).toBe('true');
        expect(cell('Ligne 1 trou 9').getAttribute('aria-invalid')).toBe(
            'true',
        );

        await userEvent.clear(cell('Ligne 1 trou 9'));
        await userEvent.type(cell('Ligne 1 trou 9'), '5');

        expect(problemMessages()).not.toContain('Somme aller 36 ≠ OUT 38');
        expect(cell('Ligne 1 OUT').getAttribute('aria-invalid')).toBeNull();
    });
});

describe('RoundEditor, layout of the design', () => {
    it('puts PARCOURS and TEES on one row, DATE under them', () => {
        render(editor());

        const course = cell('Parcours').closest('[data-slot="field"]');
        const tees = cell('Tees').closest('[data-slot="field"]');
        const date = screen
            .getByRole('button', { name: 'Date' })
            .closest('[data-slot="field"]');

        expect(course?.parentElement).toBe(tees?.parentElement);
        expect(course?.parentElement).toHaveClass('grid-cols-[1fr_110px]');
        expect(date?.parentElement).not.toBe(course?.parentElement);
        // The date drawn as the other fields: surface-1, Geist 500 16 px.
        expect(screen.getByRole('button', { name: 'Date' })).toHaveClass(
            'bg-surface-1',
            'text-body-l',
        );
    });

    it('draws the list of a line without player with an amber border and « Choisir un joueur »', () => {
        render(
            editor({
                initial: cardEditorState({
                    ...REFERENCE_CARD,
                    lines: [
                        {
                            playerId: null,
                            strokes: REFERENCE_CARD.lines[0].strokes,
                        },
                        REFERENCE_CARD.lines[1],
                    ],
                }),
            }),
        );

        const unassigned = screen.getByRole('combobox', {
            name: 'Joueur de la ligne 1',
        }) as HTMLSelectElement;
        const assigned = screen.getByRole('combobox', {
            name: 'Joueur de la ligne 2',
        });

        expect(unassigned.selectedOptions[0].textContent).toBe(
            'Choisir un joueur',
        );
        expect(
            unassigned.closest('[data-slot="native-select-wrapper"]'),
        ).toHaveClass('*:data-[slot=native-select]:border-accent');
        expect(
            assigned.closest('[data-slot="native-select-wrapper"]'),
        ).not.toHaveClass('*:data-[slot=native-select]:border-accent');
    });

    it('heads the grid with the name of each line in its player colour, amber without player', () => {
        render(
            editor({
                initial: cardEditorState({
                    ...REFERENCE_CARD,
                    lines: [
                        ...REFERENCE_CARD.lines,
                        { playerId: null, strokes: Array(18).fill(null) },
                    ],
                }),
            }),
        );

        const names = [
            ...screen
                .getByTestId('grid-head')
                .querySelectorAll('[data-slot="grid-line-name"]'),
        ];

        expect(names.map((name) => name.textContent)).toEqual([
            'Gab',
            'Manu',
            'L3',
        ]);
        expect(names[0]).toHaveClass('text-player-1');
        expect(names[1]).toHaveClass('text-player-2');
        expect(names[2]).toHaveClass('text-accent');
    });

    it('shows the rows ALL. after hole 9, RET. and TOT. after hole 18, with their totals', () => {
        render(editor({ initial: cardEditorState(REFERENCE_CARD) }));

        const rows = [
            ...screen
                .getByRole('region', { name: 'Carte à corriger' })
                .querySelectorAll('[data-slot="grid-row"]'),
        ].map(
            (row) =>
                row.querySelector('[data-slot="grid-row-label"]')?.textContent,
        );

        expect(rows).toEqual([
            '1',
            '2',
            '3',
            '4',
            '5',
            '6',
            '7',
            '8',
            '9',
            'ALL.',
            '10',
            '11',
            '12',
            '13',
            '14',
            '15',
            '16',
            '17',
            '18',
            'RET.',
            'TOT.',
        ]);
        expect(cell('Ligne 1 OUT').value).toBe('38');
        expect(cell('Ligne 1 IN').value).toBe('34');
        expect(cell('Ligne 1 TOT').value).toBe('72');
        expect(cell('Par OUT').value).toBe('36');
        expect(cell('Par TOT').value).toBe('71');
    });

    it('paints a score under par in `signal`, a score at par on `surface-1`', () => {
        render(
            editor({
                initial: cardEditorState({
                    ...REFERENCE_CARD,
                    lines: [
                        {
                            playerId: GAB,
                            strokes: [
                                null,
                                ...REFERENCE_CARD.lines[0].strokes.slice(1),
                            ],
                        },
                    ],
                }),
            }),
        );

        // Gab's 4 at hole 7, par 5: under par.
        expect(cell('Ligne 1 trou 7')).toHaveClass(
            'bg-signal/22',
            'border-signal/50',
        );
        expect(cell('Ligne 1 trou 2')).toHaveClass(
            'bg-surface-1',
            'border-rule',
        );
    });

    it('paints an empty score of a saved round in amber from the opening', () => {
        render(
            <RoundEditor
                initial={cardEditorState({
                    ...REFERENCE_CARD,
                    lines: [
                        {
                            playerId: GAB,
                            strokes: [
                                null,
                                ...REFERENCE_CARD.lines[0].strokes.slice(1),
                            ],
                        },
                    ],
                })}
                players={PLAYERS}
                courses={[]}
                action={store()}
                flagUntouchedEmpty
            />,
        );

        expect(cell('Ligne 1 trou 1')).toHaveClass(
            'bg-accent/8',
            'border-accent/60',
        );
    });

    it('leaves the untouched empty cells of a fresh entry without the error background', () => {
        render(editor());

        expect(cell('Ligne 1 trou 1')).not.toHaveClass('bg-accent/8');
        expect(cell('Ligne 1 trou 1')).toHaveClass('bg-surface-1');
        expect(cell('Par trou 1')).not.toHaveClass('bg-accent/8');
    });

    it('draws the par and index cells with the `rule` outline (DO23)', () => {
        render(editor({ initial: cardEditorState(REFERENCE_CARD) }));

        expect(cell('Par trou 1')).toHaveClass('border-rule');
        expect(cell('Index trou 1')).toHaveClass('border-rule');
        expect(cell('Par trou 1')).not.toHaveClass('border-rule-soft');
    });

    it('keeps the distances in the « Distances » block, closed under the grid', async () => {
        render(editor({ initial: cardEditorState(REFERENCE_CARD) }));

        const block = screen
            .getByText('Distances')
            .closest('details') as HTMLDetailsElement;

        expect(block.open).toBe(false);
        expect(
            screen
                .getByRole('region', { name: 'Carte à corriger' })
                .compareDocumentPosition(block) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();

        await openDistances();

        expect(block.open).toBe(true);
        expect(
            within(block)
                .getAllByRole('textbox')
                .map((input) => input.getAttribute('aria-label')),
        ).toEqual(
            Array.from(
                { length: 18 },
                (_, hole) => `Distance trou ${hole + 1}`,
            ),
        );
        expect(cell('Distance trou 1').value).toBe('420');
    });

    it('still counts and lists a distance of 1200 once the block is closed', async () => {
        render(editor({ initial: cardEditorState(REFERENCE_CARD) }));

        await openDistances();
        await userEvent.clear(cell('Distance trou 3'));
        await userEvent.type(cell('Distance trou 3'), '1200');
        await openDistances();

        expect(
            (
                screen
                    .getByText('Distances')
                    .closest('details') as HTMLDetailsElement
            ).open,
        ).toBe(false);
        expect(problemMessages()).toEqual(['Distance 1200 hors de 1 à 999']);
        expect(saveButton().textContent).toBe('1 problème à corriger');
    });
});
