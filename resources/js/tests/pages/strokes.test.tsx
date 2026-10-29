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

import StrokesIndex from '@/pages/strokes/index';
import { GAB, MANU, SEAN, STROKE_INDEXES } from '@/tests/reference-round';

// Spec match-strokes « Page Coups protégée et sans écriture », « Préparation
// d'une partie », « Modes de calcul des coups », « Handicap de jeu négatif »,
// « Total des coups d'un joueur », « Grille des coups par trou » and « Rating
// du parcours choisi » (MODIFIED by visual-redesign), « Mise en page de la
// page Coups » and « Bloc de résultat des coups ». Head needs the head manager
// of createInertiaApp and renders nothing here; usePage is simulated for the
// top row.
// Mutations: « 1 coups » for one stroke → the « Complet » test goes red; the
// fifth player still checkable (MAX_PLAYERS check dropped) → the fifth player
// test goes red; the course rating ignored (`undefined` passed to
// matchStrokes) → the BETHPAGE BLACK test goes red; a grid shown at 0 stroke
// → the PAYNES VALLEY test goes red; the list opened on « Choisir un
// parcours » (no course chosen) → the opening test goes red; the rating line
// written « Rating 71,4 » / « Slope 144 » apart → the rating line test goes
// red; a stroke given on the hole of index 17 (allocation from index 2) → the
// 16 strokes grid test reads a stroke on hole 14 and goes red; the hint of
// « Écart » kept under « Complet » → the mode test goes red.

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ url: '/strokes', props: {} }),
}));

const ALEX = '0190a6b2-7c3e-7000-8000-000000000011';
const RORY = '0190a6b2-7c3e-7000-8000-000000000012';
const TOM = '0190a6b2-7c3e-7000-8000-000000000013';

const players = [
    { id: ALEX, name: 'Alex', indexT: 125, colorSlot: 3 },
    { id: GAB, name: 'Gab', indexT: 10, colorSlot: 0 },
    { id: MANU, name: 'Manu', indexT: 174, colorSlot: 1 },
    { id: RORY, name: 'Rory', indexT: -16, colorSlot: 4 },
    { id: SEAN, name: 'Sean', indexT: null, colorSlot: 2 },
    { id: TOM, name: 'Tom', indexT: 50, colorSlot: 5 },
];

const courses = [
    {
        courseName: 'BETHPAGE BLACK',
        tees: 'Red',
        par: 71,
        ratingT: 714,
        slope: 144,
        strokeIndexes: STROKE_INDEXES,
    },
    {
        courseName: 'PAYNES VALLEY',
        tees: 'Blue',
        par: 72,
        ratingT: null,
        slope: null,
        strokeIndexes: [
            1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18,
        ],
    },
];

const PROMPT = 'Choisissez au moins 2 joueurs avec un index.';

let spies: MockInstance[];

beforeEach(() => {
    spies = [
        vi.spyOn(router, 'visit'),
        vi.spyOn(router, 'get'),
        vi.spyOn(router, 'post'),
        vi.spyOn(router, 'reload'),
        vi.spyOn(globalThis, 'fetch'),
    ];
});

afterEach(() => {
    vi.restoreAllMocks();
});

/** The box of one player, named by its row: the name, then the index. */
function checkbox(name: string): HTMLInputElement {
    return within(screen.getByTestId(`player-choice-${name}`)).getByRole(
        'checkbox',
    ) as HTMLInputElement;
}

async function choose(names: string[], course: string) {
    for (const name of names) {
        await userEvent.click(checkbox(name));
    }

    await userEvent.selectOptions(screen.getByLabelText('Parcours'), course);
}

/** The result blocks, in the order shown. */
function blocks(): HTMLElement[] {
    return within(
        screen.getByRole('list', { name: 'Coups reçus' }),
    ).getAllByRole('listitem');
}

/** The name and total of each chosen player, « Manu reçoit 16 coups ». */
function totals(): string[] {
    return blocks().map(
        (block) =>
            `${within(block).getByTestId('strokes-name').textContent} ${within(block).getByTestId('strokes-total').textContent}`,
    );
}

/** The result block of one player. */
function result(name: string): HTMLElement {
    return blocks().find(
        (block) =>
            within(block).getByTestId('strokes-name').textContent === name,
    ) as HTMLElement;
}

describe('Strokes page', () => {
    it('shows « Manu » and « Index 17,4 » on his row', () => {
        render(<StrokesIndex players={players} courses={courses} />);

        const row = screen.getByTestId('player-choice-Manu');

        expect(within(row).getByText('Manu').textContent).toBe('Manu');
        expect(within(row).getByText('Index 17,4').textContent).toBe(
            'Index 17,4',
        );
    });

    // Mutation: the space between the name and the index removed → the box
    // is named « GabIndex 1,0 » and this test goes red.
    it('names each box by the player and his index, read apart', () => {
        render(<StrokesIndex players={players} courses={courses} />);

        expect(screen.getByRole('checkbox', { name: 'Gab Index 1,0' })).toBe(
            checkbox('Gab'),
        );
        expect(
            screen.getByRole('checkbox', { name: "Sean pas encore d'index" }),
        ).toBe(checkbox('Sean'));
    });

    it('draws the row of Gab at 52 px with the box of 22 px, his swatch and « Index 1,0 » in grey', async () => {
        render(<StrokesIndex players={players} courses={courses} />);

        await userEvent.click(checkbox('Gab'));

        const row = screen.getByTestId('player-choice-Gab');

        expect(row.className).toContain('min-h-13');
        expect(checkbox('Gab').checked).toBe(true);
        expect(checkbox('Gab').className).toContain('checked:bg-ink');
        expect(
            row.querySelector('[data-slot="checkbox-wrapper"]')?.className,
        ).toContain('size-[22px]');
        expect(
            row.querySelector('.bg-player-1[aria-hidden="true"]'),
        ).not.toBeNull();
        expect(within(row).getByText('Gab').className).toContain('text-label');
        expect(within(row).getByText('Index 1,0').className).toContain(
            'text-meta',
        );
    });

    it('greys Sean out with « pas encore d’index », his box disabled', () => {
        render(<StrokesIndex players={players} courses={courses} />);

        const row = screen.getByTestId('player-choice-Sean');

        expect(row.getAttribute('data-disabled')).toBe('true');
        expect(row.classList.contains('opacity-45')).toBe(true);
        expect(within(row).getByText("pas encore d'index").textContent).toBe(
            "pas encore d'index",
        );
        expect(checkbox('Sean')).toBeDisabled();
    });

    it('asks for at least 2 players with an index, in a dashed frame, while only one is checked', async () => {
        render(<StrokesIndex players={players} courses={courses} />);

        await choose(['Gab'], 'BETHPAGE BLACK / Red');

        expect(screen.getByText(PROMPT).className).toContain('border-dashed');
        expect(screen.queryByTestId('strokes-total')).toBeNull();
    });

    it('gives Gab no stroke and no grid, Manu 16 strokes, on PAYNES VALLEY in « Écart »', async () => {
        render(<StrokesIndex players={players} courses={courses} />);

        await choose(['Gab', 'Manu'], 'PAYNES VALLEY / Blue');

        expect(totals()).toEqual([
            'Gab ne reçoit aucun coup',
            'Manu reçoit 16 coups',
        ]);
        expect(within(result('Gab')).queryByTestId('strokes-holes')).toBeNull();
        expect(
            within(result('Manu')).getByTestId('strokes-holes'),
        ).toBeInTheDocument();
        expect(screen.queryByText(PROMPT)).toBeNull();
    });

    it('gives Gab 1 stroke and Manu 17 in « Complet »', async () => {
        render(<StrokesIndex players={players} courses={courses} />);

        await choose(['Gab', 'Manu'], 'PAYNES VALLEY / Blue');
        await userEvent.click(screen.getByRole('radio', { name: 'Complet' }));

        expect(totals()).toEqual(['Gab reçoit 1 coup', 'Manu reçoit 17 coups']);
    });

    it('gives Manu 21 strokes on BETHPAGE BLACK / Red at 71,4 / 144', async () => {
        render(<StrokesIndex players={players} courses={courses} />);

        await choose(['Gab', 'Manu'], 'BETHPAGE BLACK / Red');

        expect(totals()).toEqual([
            'Gab ne reçoit aucun coup',
            'Manu reçoit 21 coups',
        ]);
    });

    it('opens the list of the courses on the first known course, « BETHPAGE BLACK / Red »', () => {
        render(<StrokesIndex players={players} courses={courses} />);

        const list = screen.getByLabelText('Parcours') as HTMLSelectElement;

        expect(list.selectedOptions[0].textContent).toBe(
            'BETHPAGE BLACK / Red',
        );
        expect(screen.queryByText('Choisir un parcours')).toBeNull();
    });

    it('writes « rating 71,4 · slope 144 » in a grey underlined link to /courses', async () => {
        render(<StrokesIndex players={players} courses={courses} />);

        await choose(['Gab', 'Manu'], 'BETHPAGE BLACK / Red');

        const link = screen.getByTestId('course-rating');

        expect(link.getAttribute('href')).toBe('/courses');
        expect(link.textContent).toBe('rating 71,4 · slope 144');
        expect(link.className).toContain('underline');
        expect(link.className).toContain('text-meta-12');
        expect(link.className).toContain('text-ink-2');
    });

    it('writes « sans rating (slope 113) » for PAYNES VALLEY, in a link to /courses', async () => {
        render(<StrokesIndex players={players} courses={courses} />);

        await choose([], 'PAYNES VALLEY / Blue');

        const link = screen.getByTestId('course-rating');

        expect(link.getAttribute('href')).toBe('/courses');
        expect(link.textContent).toBe('sans rating (slope 113)');
    });

    it('offers « Écart » and « Complet » in a segmented control, each with its explanation', async () => {
        render(<StrokesIndex players={players} courses={courses} />);

        const ecart = screen.getByRole('radio', { name: 'Écart' });
        const complet = screen.getByRole('radio', { name: 'Complet' });

        expect((ecart as HTMLInputElement).checked).toBe(true);
        expect(ecart.closest('[role="radiogroup"]')?.className).toContain(
            'rounded-12',
        );
        expect(
            screen.getByText(
                'Le meilleur joueur joue scratch, les autres reçoivent la différence.',
            ),
        ).toBeInTheDocument();

        await userEvent.click(complet);

        expect((complet as HTMLInputElement).checked).toBe(true);
        expect(
            screen.getByText(
                'Chaque joueur reçoit son handicap de jeu complet.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.queryByText(
                'Le meilleur joueur joue scratch, les autres reçoivent la différence.',
            ),
        ).toBeNull();
    });

    it('shows Manu « reçoit 16 coups », « 16 » at 48 px and 18 cells, holes 3 and 14 at « · » read « aucun coup »', async () => {
        render(
            <StrokesIndex
                players={players}
                courses={[
                    {
                        courseName: 'BETHPAGE BLACK',
                        tees: 'Red',
                        par: 71,
                        ratingT: null,
                        slope: null,
                        strokeIndexes: STROKE_INDEXES,
                    },
                ]}
            />,
        );

        await choose(['Gab', 'Manu'], 'BETHPAGE BLACK / Red');

        const manu = result('Manu');
        const cells = within(manu).getAllByRole('img');

        expect(within(manu).getByTestId('strokes-total').textContent).toBe(
            'reçoit 16 coups',
        );
        expect(within(manu).getByTestId('strokes-count').textContent).toBe(
            '16',
        );
        expect(within(manu).getByTestId('strokes-count').className).toContain(
            'text-display-l',
        );
        expect(cells).toHaveLength(18);
        expect(
            cells
                .filter((cell) => cell.lastElementChild?.textContent === '·')
                .map((cell) => cell.getAttribute('aria-label')),
        ).toEqual(['Trou 3 : aucun coup', 'Trou 14 : aucun coup']);
    });

    it('makes a player at −1,6 « rend 2 coups » in « Complet »', async () => {
        render(<StrokesIndex players={players} courses={courses} />);

        await choose(['Gab', 'Rory'], 'BETHPAGE BLACK / Red');
        await userEvent.click(screen.getByRole('radio', { name: 'Complet' }));

        expect(totals()).toEqual(['Gab reçoit 2 coups', 'Rory rend 2 coups']);
    });

    // Spec « Handicap de jeu négatif », scenario « Joueur plus 2 »: a player at
    // −1,6 and Gab at 1,0 on BETHPAGE BLACK / Red without rating, in
    // « Complet ». Mutation: the given-back strokes put on the lowest indexes
    // (the index reversal dropped in allocateStrokes) → the −1 lands on holes
    // 15 and 4 and this test goes red.
    it('makes a player at −1,6 give back 1 stroke on holes 3 and 14 of BETHPAGE BLACK / Red without rating, Gab « reçoit 1 coup »', async () => {
        render(
            <StrokesIndex
                players={[
                    { id: GAB, name: 'Gab', indexT: 10, colorSlot: 0 },
                    { id: RORY, name: 'Rory', indexT: -16, colorSlot: 4 },
                ]}
                courses={[
                    {
                        courseName: 'BETHPAGE BLACK',
                        tees: 'Red',
                        par: 71,
                        ratingT: null,
                        slope: null,
                        strokeIndexes: STROKE_INDEXES,
                    },
                ]}
            />,
        );

        await choose(['Rory', 'Gab'], 'BETHPAGE BLACK / Red');
        await userEvent.click(screen.getByRole('radio', { name: 'Complet' }));

        expect(totals()).toEqual(['Gab reçoit 1 coup', 'Rory rend 2 coups']);
        expect(
            within(result('Rory')).getByTestId('strokes-count').textContent,
        ).toBe('\u22122');
        expect(
            within(result('Rory'))
                .getAllByRole('img')
                .map((cell) => cell.getAttribute('aria-label')),
        ).toEqual([
            'Trou 1 : aucun coup',
            'Trou 2 : aucun coup',
            'Trou 3 : rend 1 coup',
            'Trou 4 : aucun coup',
            'Trou 5 : aucun coup',
            'Trou 6 : aucun coup',
            'Trou 7 : aucun coup',
            'Trou 8 : aucun coup',
            'Trou 9 : aucun coup',
            'Trou 10 : aucun coup',
            'Trou 11 : aucun coup',
            'Trou 12 : aucun coup',
            'Trou 13 : aucun coup',
            'Trou 14 : rend 1 coup',
            'Trou 15 : aucun coup',
            'Trou 16 : aucun coup',
            'Trou 17 : aucun coup',
            'Trou 18 : aucun coup',
        ]);
        expect(
            within(result('Rory')).getByRole('img', {
                name: 'Trou 3 : rend 1 coup',
            }).lastElementChild?.textContent,
        ).toBe('\u22121');
        expect(
            within(result('Rory')).getByRole('img', {
                name: 'Trou 14 : rend 1 coup',
            }).lastElementChild?.textContent,
        ).toBe('\u22121');
    });

    // Spec « Total des coups d'un joueur », scenario « Le meilleur joueur en
    // mode Écart »: Gab at 0,6, Manu and Sean, in « Écart ». Mutation: the gap
    // taken from 0 instead of the lowest playing handicap (`lowest = 0` in
    // matchStrokes) → « Gab reçoit 1 coup » with a grid and this test goes red.
    it('gives Gab at 0,6 no stroke and no grid in « Écart » with Manu and Sean', async () => {
        render(
            <StrokesIndex
                players={[
                    { id: GAB, name: 'Gab', indexT: 6, colorSlot: 0 },
                    { id: MANU, name: 'Manu', indexT: 174, colorSlot: 1 },
                    { id: SEAN, name: 'Sean', indexT: 125, colorSlot: 2 },
                ]}
                courses={courses}
            />,
        );

        await choose(['Manu', 'Sean', 'Gab'], 'PAYNES VALLEY / Blue');

        expect(totals()).toEqual([
            'Gab ne reçoit aucun coup',
            'Manu reçoit 16 coups',
            'Sean reçoit 12 coups',
        ]);
        expect(within(result('Gab')).queryByTestId('strokes-holes')).toBeNull();
    });

    it('disables the fifth box once four are checked, and enables it again after unchecking one', async () => {
        render(<StrokesIndex players={players} courses={courses} />);

        for (const name of ['Alex', 'Gab', 'Manu', 'Rory']) {
            await userEvent.click(checkbox(name));
        }

        expect(checkbox('Tom')).toBeDisabled();
        await userEvent.click(checkbox('Tom'));
        expect(checkbox('Tom').checked).toBe(false);

        await userEvent.click(checkbox('Alex'));

        expect(checkbox('Tom')).toBeEnabled();
        await userEvent.click(checkbox('Tom'));
        expect(checkbox('Tom').checked).toBe(true);
    });

    it('shows « Aucun parcours : enregistrez d’abord un round. » without course', () => {
        render(<StrokesIndex players={players} courses={[]} />);

        expect(
            screen.getByText("Aucun parcours : enregistrez d'abord un round.")
                .textContent,
        ).toBe("Aucun parcours : enregistrez d'abord un round.");
        expect(screen.queryByLabelText('Parcours')).toBeNull();
    });

    it('sends no request while players, course and mode are chosen', async () => {
        render(<StrokesIndex players={players} courses={courses} />);

        await choose(['Gab', 'Manu'], 'BETHPAGE BLACK / Red');
        await userEvent.click(screen.getByRole('radio', { name: 'Complet' }));

        expect(totals()).toEqual([
            'Gab reçoit 2 coups',
            'Manu reçoit 23 coups',
        ]);
        expect(spies.map((spy) => spy.mock.calls.length)).toEqual([
            0, 0, 0, 0, 0,
        ]);
    });
});
