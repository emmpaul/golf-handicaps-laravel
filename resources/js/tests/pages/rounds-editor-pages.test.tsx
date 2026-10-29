import { router } from '@inertiajs/react';
import type { MockInstance } from 'vite-plus/test';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';

import RoundsCreate from '@/pages/rounds/create';
import RoundsEdit from '@/pages/rounds/edit';
import { holeCells } from '@/tests/grid-cells';
import {
    DISTANCES,
    GAB,
    GAB_STROKES,
    MANU,
    PARS,
    PLAYERS,
    REFERENCE_CARD,
    ROUND,
    STROKE_INDEXES,
} from '@/tests/reference-round';

// Spec rounds « Saisie entièrement manuelle » and « Correction d'un round
// enregistré »: the two pages mount the same RoundEditor, with the props of
// RoundController@create and @edit, and send through the Wayfinder actions
// RoundController.store and RoundController.update. The router's verbs are
// stubbed (jsdom cannot run a visit). Head renders nothing outside
// createInertiaApp; jsdom has no matchMedia nor ResizeObserver; the orb paints
// on a canvas jsdom cannot draw.
// Spec rounds « Mise en page de l'éditeur » and app-shell « Navigation masquée
// pendant l'édition »: titles « Nouveau round » and « Modifier le round »,
// back links « Rounds » and « Round », the pages declared in the editor
// chrome of the layout (no tab bar, no Importer).
// Mutations: `edit` sending in POST (`{ url: update.url(id), method: 'post' }`) → the edit test
// sees no PUT and goes red; `create` starting from a fixed date instead of
// `today` → the create test goes red; `edit` starting from an empty editor →
// the edit test loses its two lines; the title « Saisie manuelle » kept → the
// create title test goes red; the back link of the correction pointed at
// /rounds → the edit back link test goes red; `layout` dropped from a page →
// its chrome test reads undefined and goes red.

vi.mock('thinking-orbs', () => ({
    ThinkingOrb: () => <canvas data-testid="orb" />,
}));

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ url: '/rounds/new', props: {} }),
}));

const backLink = () =>
    within(
        document.querySelector('[data-slot="page-top-row"]') as HTMLElement,
    ).getByRole('link');

let post: MockInstance<typeof router.post>;
let put: MockInstance<typeof router.put>;

beforeEach(() => {
    vi.stubGlobal('matchMedia', () => ({
        matches: false,
        addEventListener: () => {},
        removeEventListener: () => {},
    }));
    post = vi.spyOn(router, 'post').mockImplementation(() => {});
    put = vi.spyOn(router, 'put').mockImplementation(() => {});
});

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

const selectedPlayer = (line: number) =>
    (
        screen.getByRole('combobox', {
            name: `Joueur de la ligne ${line}`,
        }) as HTMLSelectElement
    ).value;

describe('Manual entry page', () => {
    it('starts from `today` and one empty line', () => {
        render(
            <RoundsCreate today="2026-10-12" players={PLAYERS} courses={[]} />,
        );

        expect(screen.getByRole('heading', { level: 1 }).textContent).toBe(
            'Nouveau round',
        );
        expect(backLink().textContent).toBe('Rounds');
        expect(backLink().getAttribute('href')).toBe('/rounds');
        expect(screen.getByRole('button', { name: 'Date' }).textContent).toBe(
            '12 octobre 2026',
        );
        expect(
            screen
                .getAllByRole('combobox', { name: /^Joueur de la ligne \d$/ })
                .map((select) => (select as HTMLSelectElement).value),
        ).toEqual(['']);
        expect(
            (
                screen.getByRole('textbox', {
                    name: 'Ligne 1 trou 1',
                }) as HTMLInputElement
            ).value,
        ).toBe('');
    });

    it('sends the card to POST /rounds', async () => {
        const course = {
            courseName: 'BETHPAGE BLACK',
            tees: 'Red',
            pars: PARS,
            strokeIndexes: STROKE_INDEXES,
            distances: DISTANCES,
        };

        render(
            <RoundsCreate
                today="2026-10-17"
                players={PLAYERS}
                courses={[course]}
            />,
        );

        await userEvent.selectOptions(
            screen.getByRole('combobox', { name: 'Parcours connu' }),
            'BETHPAGE BLACK / Red',
        );
        await userEvent.selectOptions(
            screen.getByRole('combobox', { name: 'Joueur de la ligne 1' }),
            'Gab',
        );

        const holes = holeCells('Ligne 1');

        for (const [hole, strokes] of GAB_STROKES.entries()) {
            await userEvent.type(holes[hole], `${strokes}`);
        }

        await userEvent.click(
            screen.getByRole('button', { name: 'Enregistrer' }),
        );

        expect(put).not.toHaveBeenCalled();
        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0][0]).toEqual({
            url: '/rounds',
            method: 'post',
        });
        expect(post.mock.calls[0][1]).toEqual({
            ...REFERENCE_CARD,
            lines: [REFERENCE_CARD.lines[0]],
            force: false,
        });
    });
});

describe('Correction page', () => {
    const round = { id: ROUND, ...REFERENCE_CARD };

    it('starts from the round with its two lines', () => {
        render(<RoundsEdit round={round} players={PLAYERS} courses={[]} />);

        expect(screen.getByRole('heading', { level: 1 }).textContent).toBe(
            'Modifier le round',
        );
        expect(backLink().textContent).toBe('Round');
        expect(backLink().getAttribute('href')).toBe(`/rounds/${ROUND}`);
        expect(screen.getByRole('button', { name: 'Date' }).textContent).toBe(
            '17 octobre 2026',
        );
        expect(
            (
                screen.getByRole('textbox', {
                    name: 'Parcours',
                }) as HTMLInputElement
            ).value,
        ).toBe('BETHPAGE BLACK');
        expect([selectedPlayer(1), selectedPlayer(2)]).toEqual([GAB, MANU]);
        expect(
            screen.queryByRole('combobox', { name: 'Joueur de la ligne 3' }),
        ).toBeNull();
        expect(
            (
                screen.getByRole('textbox', {
                    name: 'Ligne 2 TOT',
                }) as HTMLInputElement
            ).value,
        ).toBe('88');
    });

    it('sends the corrected card to PUT /rounds/{id}', async () => {
        render(<RoundsEdit round={round} players={PLAYERS} courses={[]} />);

        await userEvent.click(
            screen.getByRole('button', { name: 'Enregistrer' }),
        );

        expect(post).not.toHaveBeenCalled();
        expect(put).toHaveBeenCalledTimes(1);
        expect(put.mock.calls[0][0]).toEqual({
            url: `/rounds/${ROUND}`,
            method: 'put',
        });
        expect(put.mock.calls[0][1]).toEqual({
            ...REFERENCE_CARD,
            force: false,
        });
    });
});

describe('Chrome of the editor pages', () => {
    it('declares the editor chrome of the layout on both pages', () => {
        expect(RoundsCreate.layout).toEqual({ chrome: 'editor' });
        expect(RoundsEdit.layout).toEqual({ chrome: 'editor' });
    });
});
