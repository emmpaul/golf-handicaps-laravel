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
        strokeIndexes: STROKE_INDEXES,
    },
];

type VisitOptions = {
    data?: Record<string, string>;
    onStart?: (visit: unknown) => void;
    onError?: (errors: Record<string, string>) => void;
};

let put: MockInstance<typeof router.put>;
let remove: MockInstance<typeof router.delete>;

beforeEach(() => {
    put = vi.spyOn(router, 'put').mockImplementation(() => {});
    remove = vi.spyOn(router, 'delete').mockImplementation(() => {});
});

afterEach(() => {
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
        render(<CoursesIndex courses={courses} />);

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
        render(<CoursesIndex courses={courses} />);

        expect(
            within(row(PAYNES)).getByTestId('course-rating').textContent,
        ).toBe('sans rating');
        expect(screen.getByLabelText(`Rating de ${PAYNES}`)).toHaveValue('');
        expect(screen.getByLabelText(`Slope de ${PAYNES}`)).toHaveValue('');
    });

    it('names each field after its course and tees', () => {
        render(<CoursesIndex courses={courses} />);

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
        render(<CoursesIndex courses={[]} />);

        expect(
            screen.getByText("Aucun parcours : enregistrez d'abord un round.")
                .textContent,
        ).toBe("Aucun parcours : enregistrez d'abord un round.");
        expect(screen.queryByRole('list', { name: 'Parcours' })).toBeNull();
    });

    it('puts the typed rating of its row to /courses/rating', async () => {
        render(<CoursesIndex courses={courses} />);

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

        render(<CoursesIndex courses={courses} />);

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

        render(<CoursesIndex courses={courses} />);

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

        render(<CoursesIndex courses={courses} />);
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

        render(<CoursesIndex courses={courses} />);

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
        render(<CoursesIndex courses={courses} />);

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
        render(<CoursesIndex courses={courses} />);

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

        render(<CoursesIndex courses={courses} />);

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
        render(<CoursesIndex courses={courses} />);

        const title = screen.getByText('Comment l’index est calculé');

        expect(title.tagName).toBe('SUMMARY');
        expect((title.closest('details') as HTMLDetailsElement).open).toBe(
            false,
        );
    });
});
