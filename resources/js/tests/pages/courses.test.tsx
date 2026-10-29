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

import { STROKE_INDEXES } from '@/tests/reference-round';
import CoursesIndex from '@/pages/courses/index';
import type { CourseWithLookup } from '@/pages/courses/index';

// Spec course-ratings « Page Parcours », « Saisie et correction à la main »,
// « Retrait d'une rating », « Champs nommés par parcours » and loading-feedback
// « Attente de la saisie d'une rating », « Attente du retrait d'une rating ».
// Each row submits through its own useForm, hence through the Inertia router;
// only the router's verbs are stubbed (jsdom cannot run a visit), and the
// visit callbacks they receive (onStart, onError) drive the row's own state.
// Head needs the head manager of createInertiaApp and renders nothing here;
// the orb paints on a canvas jsdom cannot draw.
// Mutations: one sending state for the whole page → the « other row stays
// usable » assertions go red; accessible names without the tees (« Rating de
// BETHPAGE BLACK ») → the naming test goes red; « Retirer la rating » shown on
// a row without rating → the removal presence test goes red; « Enregistrer »
// left enabled during the removal → the removal pending test goes red;
// `aria-describedby` dropped → the error test goes red; ComputationHelp
// removed → the explanation test goes red.

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    Head: () => null,
}));

vi.mock('thinking-orbs', () => ({
    ThinkingOrb: () => <canvas data-testid="orb" />,
}));

const BETHPAGE_ID = '0190a6b2-7c3e-7000-8000-0000000000d1';
const PAYNES_ID = '0190a6b2-7c3e-7000-8000-0000000000d2';
const GOLD_ID = '0190a6b2-7c3e-7000-8000-0000000000d3';
const SANCTUARY_ID = '0190a6b2-7c3e-7000-8000-0000000000d4';
const CALA_ID = '0190a6b2-7c3e-7000-8000-0000000000d5';

/** The five tees of the SGT card 30 (tests/fixtures/sgt/scorecard_30.jpg). */
const TEES_30 = [
    { name: 'Blue', ratingT: 775, slope: 155 },
    { name: 'White', ratingT: 742, slope: 148 },
    { name: 'Red', ratingT: 714, slope: 144 },
    { name: 'Junior', ratingT: 710, slope: 120 },
    { name: 'PAR3', ratingT: 540, slope: 120 },
];

/** A course without row of course_ratings: no lookup, no SGT course. */
function course(
    courseName: string,
    tees: string,
    overrides: Partial<CourseWithLookup> = {},
): CourseWithLookup {
    return {
        courseName,
        tees,
        par: 71,
        ratingT: null,
        slope: null,
        strokeIndexes: STROKE_INDEXES,
        id: null,
        status: null,
        label: null,
        note: null,
        source: null,
        sgtCourseName: null,
        sgtCourseLocation: null,
        proposal: null,
        teesRead: [],
        candidates: [],
        scorecardUrl: null,
        courseViewUrl: null,
        ...overrides,
    };
}

const courses = [
    course('BETHPAGE BLACK', 'Red', {
        id: BETHPAGE_ID,
        status: 'confirmed',
        label: 'Confirmé',
        ratingT: 714,
        slope: 144,
        source: 'manual',
    }),
    course('PAYNES VALLEY', 'Blue', { par: 72 }),
];

type VisitOptions = {
    data?: Record<string, string>;
    only?: string[];
    preserveScroll?: boolean;
    onFinish?: (visit: unknown) => void;
    onStart?: (visit: unknown) => void;
    onError?: (errors: Record<string, string>) => void;
};

let put: MockInstance<typeof router.put>;
let remove: MockInstance<typeof router.delete>;
let post: MockInstance<typeof router.post>;
let reload: MockInstance<(options: VisitOptions) => void>;

beforeEach(() => {
    post = vi.spyOn(router, 'post').mockImplementation(() => {});
    // The poll of Inertia reloads through the router's doReload.
    reload = vi
        .spyOn(
            router as unknown as {
                doReload: (options: VisitOptions) => void;
            },
            'doReload',
        )
        .mockImplementation(() => {});
    put = vi.spyOn(router, 'put').mockImplementation(() => {});
    remove = vi.spyOn(router, 'delete').mockImplementation(() => {});
});

afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
});

/** The row of a course, named by its course and tees. */
function row(label: string): HTMLElement {
    return screen.getByRole('listitem', { name: label });
}

const BETHPAGE = 'BETHPAGE BLACK / Red';
const PAYNES = 'PAYNES VALLEY / Blue';

describe('Courses page', () => {
    it('shows « BETHPAGE BLACK », « Tees Red », « Par 71 », « 71,4 / 144 » and the prefilled fields', () => {
        render(<CoursesIndex courses={courses} lookupPending={false} />);

        const bethpage = row(BETHPAGE);

        expect(
            within(bethpage).getByRole('heading', { level: 2 }).textContent,
        ).toBe('BETHPAGE BLACK');
        expect(within(bethpage).getByText('Tees Red').textContent).toBe(
            'Tees Red',
        );
        expect(within(bethpage).getByText('Par 71').textContent).toBe('Par 71');
        expect(within(bethpage).getByTestId('course-rating').textContent).toBe(
            '71,4 / 144',
        );
        expect(screen.getByLabelText(`Rating de ${BETHPAGE}`)).toHaveValue(
            '71,4',
        );
        expect(screen.getByLabelText(`Slope de ${BETHPAGE}`)).toHaveValue(
            '144',
        );
    });

    it('shows « sans rating » and empty fields for PAYNES VALLEY', () => {
        render(<CoursesIndex courses={courses} lookupPending={false} />);

        expect(
            within(row(PAYNES)).getByTestId('course-rating').textContent,
        ).toBe('sans rating');
        expect(screen.getByLabelText(`Rating de ${PAYNES}`)).toHaveValue('');
        expect(screen.getByLabelText(`Slope de ${PAYNES}`)).toHaveValue('');
    });

    it('names each field after its course and tees', () => {
        render(<CoursesIndex courses={courses} lookupPending={false} />);

        expect(
            screen
                .getAllByRole('textbox')
                .map((field) => field.getAttribute('aria-label')),
        ).toEqual([
            'Rating de BETHPAGE BLACK / Red',
            'Slope de BETHPAGE BLACK / Red',
            'Rating de PAYNES VALLEY / Blue',
            'Slope de PAYNES VALLEY / Blue',
        ]);
    });

    it("shows « Aucun parcours : enregistrez d'abord un round. » without course", () => {
        render(<CoursesIndex courses={[]} lookupPending={false} />);

        expect(
            screen.getByText("Aucun parcours : enregistrez d'abord un round.")
                .textContent,
        ).toBe("Aucun parcours : enregistrez d'abord un round.");
        expect(screen.queryByRole('list', { name: 'Parcours' })).toBeNull();
    });

    it('puts the typed rating of its row to /courses/rating', async () => {
        render(<CoursesIndex courses={courses} lookupPending={false} />);

        await userEvent.type(
            screen.getByLabelText(`Rating de ${PAYNES}`),
            '74,1',
        );
        await userEvent.type(
            screen.getByLabelText(`Slope de ${PAYNES}`),
            '140',
        );
        await userEvent.click(
            within(row(PAYNES)).getByRole('button', { name: 'Enregistrer' }),
        );

        expect(put).toHaveBeenCalledTimes(1);
        expect(put.mock.calls[0][0]).toBe('/courses/rating');
        expect(put.mock.calls[0][1]).toEqual({
            courseName: 'PAYNES VALLEY',
            tees: 'Blue',
            rating: '74,1',
            slope: '140',
        });
        expect(
            (put.mock.calls[0][2] as { preserveScroll?: boolean })
                .preserveScroll,
        ).toBe(true);
    });

    it('shows « Enregistrement de la rating… » on the sent row only, its button disabled', async () => {
        put.mockImplementation((_url, _data, options) => {
            (options as VisitOptions).onStart?.({});
        });

        render(<CoursesIndex courses={courses} lookupPending={false} />);

        await act(async () => {
            await userEvent.click(
                within(row(BETHPAGE)).getByRole('button', {
                    name: 'Enregistrer',
                }),
            );
        });

        expect(within(row(BETHPAGE)).getByRole('status').textContent).toBe(
            'Enregistrement de la rating…',
        );
        expect(
            within(row(BETHPAGE)).getByRole('button', { name: 'Enregistrer' }),
        ).toBeDisabled();
        expect(
            within(row(PAYNES)).getByRole('button', { name: 'Enregistrer' }),
        ).toBeEnabled();
        expect(within(row(PAYNES)).getByRole('status')).toBeEmptyDOMElement();
    });

    it('shows « Slope entre 55 et 155 » tied to the slope field of its row', async () => {
        put.mockImplementation((_url, _data, options) => {
            (options as VisitOptions).onError?.({
                slope: 'Slope entre 55 et 155',
            });
        });

        render(<CoursesIndex courses={courses} lookupPending={false} />);

        await userEvent.click(
            within(row(BETHPAGE)).getByRole('button', { name: 'Enregistrer' }),
        );

        const alert = within(row(BETHPAGE)).getByRole('alert');
        const slope = screen.getByLabelText(`Slope de ${BETHPAGE}`);

        expect(alert.textContent).toBe('Slope entre 55 et 155');
        expect(alert.id).not.toBe('');
        expect(slope.getAttribute('aria-describedby')).toBe(alert.id);
        expect(slope.getAttribute('aria-invalid')).toBe('true');
        expect(within(row(PAYNES)).queryByRole('alert')).toBeNull();
    });

    // Mutation: form.clearErrors() removed from save() → « Slope entre 55 et
    // 155 » stays next to « Enregistrement de la rating… » and this goes red.
    it('clears the slope error of its row when a new save starts', async () => {
        put.mockImplementationOnce((_url, _data, options) => {
            (options as VisitOptions).onError?.({
                slope: 'Slope entre 55 et 155',
            });
        }).mockImplementationOnce((_url, _data, options) => {
            (options as VisitOptions).onStart?.({});
        });

        render(<CoursesIndex courses={courses} lookupPending={false} />);
        const save = within(row(BETHPAGE)).getByRole('button', {
            name: 'Enregistrer',
        });

        await userEvent.click(save);
        expect(within(row(BETHPAGE)).getByRole('alert').textContent).toBe(
            'Slope entre 55 et 155',
        );

        await act(async () => {
            await userEvent.click(save);
        });

        expect(within(row(BETHPAGE)).getByRole('status').textContent).toBe(
            'Enregistrement de la rating…',
        );
        expect(within(row(BETHPAGE)).queryByRole('alert')).toBeNull();
        expect(
            screen
                .getByLabelText(`Slope de ${BETHPAGE}`)
                .getAttribute('aria-invalid'),
        ).toBeNull();
    });

    // Mutation: form.clearErrors() removed from remove() → the slope error
    // stays next to « Retrait de la rating… » and this goes red.
    it('clears the slope error of its row when a removal starts', async () => {
        put.mockImplementation((_url, _data, options) => {
            (options as VisitOptions).onError?.({
                slope: 'Slope entre 55 et 155',
            });
        });
        remove.mockImplementation((_url, options) => {
            (options as VisitOptions).onStart?.({});
        });

        render(<CoursesIndex courses={courses} lookupPending={false} />);

        await userEvent.click(
            within(row(BETHPAGE)).getByRole('button', { name: 'Enregistrer' }),
        );
        expect(within(row(BETHPAGE)).getByRole('alert').textContent).toBe(
            'Slope entre 55 et 155',
        );

        await act(async () => {
            await userEvent.click(
                screen.getByRole('button', {
                    name: `Retirer la rating de ${BETHPAGE}`,
                }),
            );
        });

        expect(within(row(BETHPAGE)).getByRole('status').textContent).toBe(
            'Retrait de la rating…',
        );
        expect(within(row(BETHPAGE)).queryByRole('alert')).toBeNull();
    });

    it('offers « Retirer la rating de BETHPAGE BLACK / Red », and none on PAYNES VALLEY', () => {
        render(<CoursesIndex courses={courses} lookupPending={false} />);

        expect(
            within(row(BETHPAGE)).getByRole('button', {
                name: 'Retirer la rating de BETHPAGE BLACK / Red',
            }).textContent,
        ).toBe('Retirer la rating');
        expect(
            within(row(PAYNES)).queryByRole('button', {
                name: /Retirer la rating/,
            }),
        ).toBeNull();
    });

    it('deletes the rating with the course and its tees, without confirmation', async () => {
        render(<CoursesIndex courses={courses} lookupPending={false} />);

        await userEvent.click(
            screen.getByRole('button', {
                name: 'Retirer la rating de BETHPAGE BLACK / Red',
            }),
        );

        expect(screen.queryByRole('alertdialog')).toBeNull();
        expect(remove).toHaveBeenCalledTimes(1);
        expect(remove.mock.calls[0][0]).toBe('/courses/rating');

        const options = remove.mock.calls[0][1] as VisitOptions & {
            preserveScroll?: boolean;
        };

        expect(options.data).toEqual({
            courseName: 'BETHPAGE BLACK',
            tees: 'Red',
        });
        expect(options.preserveScroll).toBe(true);
    });

    it('shows « Retrait de la rating… » and disables both buttons of the row while removing', async () => {
        remove.mockImplementation((_url, options) => {
            (options as VisitOptions).onStart?.({});
        });

        render(<CoursesIndex courses={courses} lookupPending={false} />);

        await act(async () => {
            await userEvent.click(
                screen.getByRole('button', {
                    name: 'Retirer la rating de BETHPAGE BLACK / Red',
                }),
            );
        });

        expect(within(row(BETHPAGE)).getByRole('status').textContent).toBe(
            'Retrait de la rating…',
        );
        expect(
            within(row(BETHPAGE)).getByRole('button', { name: 'Enregistrer' }),
        ).toBeDisabled();
        expect(
            within(row(BETHPAGE)).getByRole('button', {
                name: 'Retirer la rating de BETHPAGE BLACK / Red',
            }),
        ).toBeDisabled();
        expect(
            within(row(PAYNES)).getByRole('button', { name: 'Enregistrer' }),
        ).toBeEnabled();
        expect(within(row(PAYNES)).getByRole('status')).toBeEmptyDOMElement();
    });

    it('offers « Comment l’index est calculé », closed', () => {
        render(<CoursesIndex courses={courses} lookupPending={false} />);

        const title = screen.getByText('Comment l’index est calculé');

        expect(title.tagName).toBe('SUMMARY');
        expect((title.closest('details') as HTMLDetailsElement).open).toBe(
            false,
        );
    });
});

// Spec course-ratings « Statuts de la recherche », « Parcours SGT et
// proposition sur la page Parcours », « Page Parcours rafraîchie pendant une
// recherche », « Relance de la recherche », « Actions de recherche nommées par
// parcours » and loading-feedback « Attentes des actions de la recherche »,
// « Recherche en cours signalée » (design D9 of course-rating-sgt).
// Mutations:
// - the poll never stopped (no stop() once lookupPending is false) → the poll
//   test records reloads after the lookup ends and goes red;
// - accessible names without the tees (« Confirmer 71,4 / 144 pour BETHPAGE
//   BLACK », « Retenir le tee White pour BETHPAGE BLACK ») → the proposal and
//   tee tests go red;
// - « Relancer la recherche » hidden on a pending row (as in Next) → the
//   relaunch presence test goes red;
// - one sending state shared by the page → the « other rows stay usable »
//   assertion goes red;
// - the manual form keyed on the status too → the reload keeps-typing test
//   goes red.
describe('Courses page, lookup on Simulator Golf Tour', () => {
    const pending = course('BETHPAGE BLACK', 'Red', {
        id: BETHPAGE_ID,
        status: 'pending',
        label: 'Recherche en cours',
    });
    const confirmed = course('PAYNES VALLEY', 'Blue', {
        id: PAYNES_ID,
        par: 72,
        status: 'confirmed',
        label: 'Confirmé',
        ratingT: 714,
        slope: 144,
        source: 'sgt',
    });
    const failed = course('PAYNES VALLEY', 'Blue', {
        id: PAYNES_ID,
        par: 72,
        status: 'failed',
        label: 'Recherche échouée',
        note: 'SGT injoignable',
    });
    const proposed = course('BETHPAGE BLACK', 'Red', {
        id: BETHPAGE_ID,
        status: 'proposed',
        label: 'Proposition à confirmer',
        sgtCourseName: 'Bethpage Black',
        sgtCourseLocation: 'Farmingdale, NY',
        proposal: { name: 'Red', ratingT: 714, slope: 144 },
        teesRead: TEES_30,
        scorecardUrl:
            'https://simulatorgolftour.com/public/assets/courseImages/scorecards/scorecard_30.jpg',
        courseViewUrl: 'https://thecourseview.com/courses/bethpage-black',
    });
    const chooseTee = course('BETHPAGE BLACK', 'Gold', {
        id: GOLD_ID,
        status: 'choose_tee',
        label: 'Tee à choisir',
        sgtCourseName: 'Bethpage Black',
        sgtCourseLocation: 'Farmingdale, NY',
        teesRead: TEES_30,
        scorecardUrl:
            'https://simulatorgolftour.com/public/assets/courseImages/scorecards/scorecard_30.jpg',
        courseViewUrl: 'https://thecourseview.com/courses/bethpage-black',
    });
    const chooseCourse = course('SANCTUARY COVE', 'Blue', {
        id: SANCTUARY_ID,
        par: 72,
        status: 'choose_course',
        label: 'Parcours SGT à choisir',
        candidates: [
            {
                id: 477,
                name: 'Sanctuary',
                location: 'Sedalia, CO',
                par: 72,
                scorecardUrl:
                    'https://simulatorgolftour.com/public/assets/courseImages/scorecards/scorecard_477.jpg',
            },
            {
                id: 1951,
                name: 'The Pines Sanctuary Cove',
                location: 'Hope Island, Queensland, Australia',
                par: 72,
                scorecardUrl:
                    'https://simulatorgolftour.com/public/assets/courseImages/scorecards/scorecard_1951.jpg',
            },
        ],
    });
    const notFound = course('CALA NOVA', 'White', {
        id: CALA_ID,
        par: 72,
        status: 'not_found',
        label: 'Introuvable sur SGT',
    });
    const withoutRow = course('PAYNES VALLEY', 'White', { par: 72 });

    it('shows « Recherche en cours » with the pending indicator, and « 71,4 / 144 » « Confirmé » without it', () => {
        render(<CoursesIndex courses={[pending, confirmed]} lookupPending />);

        const searching = row('BETHPAGE BLACK / Red');
        const done = row('PAYNES VALLEY / Blue');

        expect(within(searching).getByTestId('course-status').textContent).toBe(
            'Recherche en cours',
        );
        expect(within(searching).getAllByTestId('orb')).toHaveLength(1);
        expect(within(done).getByTestId('course-rating').textContent).toBe(
            '71,4 / 144',
        );
        expect(within(done).getByTestId('course-status').textContent).toBe(
            'Confirmé',
        );
        expect(within(done).queryByTestId('orb')).toBeNull();
        expect(within(done).queryByTestId('course-note')).toBeNull();
    });

    it('shows « sans rating », « Recherche échouée » and « SGT injoignable »', () => {
        render(<CoursesIndex courses={[failed]} lookupPending={false} />);

        const line = row('PAYNES VALLEY / Blue');

        expect(within(line).getByTestId('course-rating').textContent).toBe(
            'sans rating',
        );
        expect(within(line).getByTestId('course-status').textContent).toBe(
            'Recherche échouée',
        );
        expect(within(line).getByTestId('course-note').textContent).toBe(
            'SGT injoignable',
        );
    });

    it('shows the SGT course, its proposal, « Confirmer » and the two links of a proposed row', () => {
        render(<CoursesIndex courses={[proposed]} lookupPending={false} />);

        const line = row('BETHPAGE BLACK / Red');

        expect(within(line).getByText('SGT : Bethpage Black').textContent).toBe(
            'SGT : Bethpage Black',
        );
        expect(within(line).getByText('Farmingdale, NY').textContent).toBe(
            'Farmingdale, NY',
        );
        expect(within(line).getByTestId('course-proposal').textContent).toBe(
            'Proposition : tee Red, 71,4 / 144',
        );
        expect(
            within(line).getByRole('button', {
                name: 'Confirmer 71,4 / 144 pour BETHPAGE BLACK / Red',
            }).textContent,
        ).toBe('Confirmer');

        const card = within(line).getByRole('link', { name: 'Carte SGT' });
        const view = within(line).getByRole('link', {
            name: 'The Course View',
        });

        expect(card.getAttribute('href')).toBe(
            'https://simulatorgolftour.com/public/assets/courseImages/scorecards/scorecard_30.jpg',
        );
        expect(view.getAttribute('href')).toBe(
            'https://thecourseview.com/courses/bethpage-black',
        );
        for (const link of [card, view]) {
            expect(link.getAttribute('target')).toBe('_blank');
            expect(link.getAttribute('rel')).toBe('noreferrer');
        }
    });

    it('shows no SGT link on a row « Introuvable sur SGT »', () => {
        render(<CoursesIndex courses={[notFound]} lookupPending={false} />);

        const line = row('CALA NOVA / White');

        expect(within(line).getByTestId('course-status').textContent).toBe(
            'Introuvable sur SGT',
        );
        expect(within(line).queryByRole('link')).toBeNull();
    });

    it('lists the five tees read, each with « Retenir », and posts the tee kept', async () => {
        render(<CoursesIndex courses={[chooseTee]} lookupPending={false} />);

        const line = row('BETHPAGE BLACK / Gold');
        const tees = within(line).getByRole('list', {
            name: 'Tees lus pour BETHPAGE BLACK / Gold',
        });

        expect(
            within(tees)
                .getAllByRole('listitem')
                .map((tee) => tee.textContent),
        ).toEqual([
            'Blue77,5 / 155Retenir',
            'White74,2 / 148Retenir',
            'Red71,4 / 144Retenir',
            'Junior71,0 / 120Retenir',
            'PAR354,0 / 120Retenir',
        ]);

        await userEvent.click(
            within(line).getByRole('button', {
                name: 'Retenir le tee White pour BETHPAGE BLACK / Gold',
            }),
        );

        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0][0]).toEqual({
            url: `/courses/ratings/${GOLD_ID}/tee`,
            method: 'post',
        });
        expect(post.mock.calls[0][1]).toEqual({ tee: 'White' });
    });

    it('lists the SGT candidates with their place, « Par 72 », their card and « Choisir »', async () => {
        render(<CoursesIndex courses={[chooseCourse]} lookupPending={false} />);

        const line = row('SANCTUARY COVE / Blue');
        const candidates = within(line).getByRole('list', {
            name: 'Parcours SGT possibles pour SANCTUARY COVE / Blue',
        });
        const items = within(candidates).getAllByRole('listitem');

        expect(items).toHaveLength(2);
        expect(within(items[0]).getByText('Sanctuary').textContent).toBe(
            'Sanctuary',
        );
        expect(within(items[0]).getByText('Sedalia, CO').textContent).toBe(
            'Sedalia, CO',
        );
        expect(
            within(items[1]).getByText('The Pines Sanctuary Cove').textContent,
        ).toBe('The Pines Sanctuary Cove');
        expect(
            within(candidates)
                .getAllByText('Par 72')
                .map((par) => par.textContent),
        ).toEqual(['Par 72', 'Par 72']);
        expect(
            within(items[1])
                .getByRole('link', { name: 'Carte SGT' })
                .getAttribute('href'),
        ).toBe(
            'https://simulatorgolftour.com/public/assets/courseImages/scorecards/scorecard_1951.jpg',
        );

        await userEvent.click(
            within(line).getByRole('button', {
                name: 'Choisir Sanctuary (Sedalia, CO)',
            }),
        );

        expect(post.mock.calls[0][0]).toEqual({
            url: `/courses/ratings/${SANCTUARY_ID}/candidate`,
            method: 'post',
        });
        expect(post.mock.calls[0][1]).toEqual({ sgtCourseId: 477 });
    });

    it('offers « Relancer la recherche » on every row with an id, pending included, and on none without', () => {
        render(
            <CoursesIndex
                courses={[pending, failed, notFound, withoutRow]}
                lookupPending
            />,
        );

        expect(
            screen
                .getAllByRole('button', { name: /^Relancer la recherche/ })
                .map((button) => button.getAttribute('aria-label')),
        ).toEqual([
            'Relancer la recherche pour BETHPAGE BLACK / Red',
            'Relancer la recherche pour PAYNES VALLEY / Blue',
            'Relancer la recherche pour CALA NOVA / White',
        ]);
        expect(
            within(row('PAYNES VALLEY / White')).queryByRole('button', {
                name: /Relancer/,
            }),
        ).toBeNull();
    });

    it('posts « Confirmer » on its Wayfinder action, « Confirmation de la rating… » on its row only, its buttons disabled', async () => {
        post.mockImplementation((_url, _data, options) => {
            (options as VisitOptions).onStart?.({});
        });

        render(
            <CoursesIndex courses={[proposed, failed]} lookupPending={false} />,
        );

        await act(async () => {
            await userEvent.click(
                screen.getByRole('button', {
                    name: 'Confirmer 71,4 / 144 pour BETHPAGE BLACK / Red',
                }),
            );
        });

        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0][0]).toEqual({
            url: `/courses/ratings/${BETHPAGE_ID}/confirmation`,
            method: 'post',
        });
        expect((post.mock.calls[0][2] as VisitOptions).preserveScroll).toBe(
            true,
        );

        const sent = row('BETHPAGE BLACK / Red');
        const other = row('PAYNES VALLEY / Blue');

        expect(within(sent).getByRole('status').textContent).toBe(
            'Confirmation de la rating…',
        );
        for (const button of within(sent).getAllByRole('button')) {
            expect(button).toBeDisabled();
        }
        for (const button of within(other).getAllByRole('button')) {
            expect(button).toBeEnabled();
        }
        expect(within(other).getByRole('status')).toBeEmptyDOMElement();

        act(() => (post.mock.calls[0][2] as VisitOptions).onFinish?.({}));
        expect(within(sent).getByRole('status')).toBeEmptyDOMElement();
        expect(
            within(sent).getByRole('button', { name: 'Enregistrer' }),
        ).toBeEnabled();
    });

    it('shows « Relance de la recherche… » while relaunching', async () => {
        post.mockImplementation((_url, _data, options) => {
            (options as VisitOptions).onStart?.({});
        });

        render(<CoursesIndex courses={[failed]} lookupPending={false} />);

        await act(async () => {
            await userEvent.click(
                screen.getByRole('button', {
                    name: 'Relancer la recherche pour PAYNES VALLEY / Blue',
                }),
            );
        });

        expect(post.mock.calls[0][0]).toEqual({
            url: `/courses/ratings/${PAYNES_ID}/lookup`,
            method: 'post',
        });

        const line = row('PAYNES VALLEY / Blue');

        expect(within(line).getByRole('status').textContent).toBe(
            'Relance de la recherche…',
        );
        expect(
            within(line).getByRole('button', {
                name: 'Relancer la recherche pour PAYNES VALLEY / Blue',
            }),
        ).toBeDisabled();
    });

    it('reloads the courses and lookupPending every 2 s while a lookup runs, and stops once none runs', async () => {
        vi.useFakeTimers();

        const { rerender } = render(
            <CoursesIndex courses={[pending]} lookupPending />,
        );

        await act(async () => {
            await vi.advanceTimersByTimeAsync(1999);
        });
        expect(reload).not.toHaveBeenCalled();

        await act(async () => {
            await vi.advanceTimersByTimeAsync(1);
        });
        expect(reload).toHaveBeenCalledTimes(1);
        expect(reload.mock.calls[0][0].only).toEqual([
            'courses',
            'lookupPending',
        ]);

        await act(async () => {
            await vi.advanceTimersByTimeAsync(2000);
        });
        expect(reload).toHaveBeenCalledTimes(2);

        rerender(<CoursesIndex courses={[proposed]} lookupPending={false} />);
        await act(async () => {
            await vi.advanceTimersByTimeAsync(10_000);
        });
        expect(reload).toHaveBeenCalledTimes(2);
    });

    it('does not reload when no lookup runs', async () => {
        vi.useFakeTimers();

        render(<CoursesIndex courses={[confirmed]} lookupPending={false} />);

        await act(async () => {
            await vi.advanceTimersByTimeAsync(10_000);
        });
        expect(reload).not.toHaveBeenCalled();
    });

    it('keeps a field being typed when a reload keeps 71,4 / 144', async () => {
        const relaunched: CourseWithLookup = {
            ...confirmed,
            status: 'pending',
            label: 'Recherche en cours',
        };
        const { rerender } = render(
            <CoursesIndex courses={[relaunched]} lookupPending />,
        );

        const slope = screen.getByLabelText('Slope de PAYNES VALLEY / Blue');

        await userEvent.clear(slope);
        await userEvent.type(slope, '150');

        rerender(
            <CoursesIndex
                courses={[
                    {
                        ...relaunched,
                        status: 'proposed',
                        label: 'Proposition à confirmer',
                    },
                ]}
                lookupPending={false}
            />,
        );

        expect(
            screen.getByLabelText('Slope de PAYNES VALLEY / Blue'),
        ).toHaveValue('150');
    });
});
