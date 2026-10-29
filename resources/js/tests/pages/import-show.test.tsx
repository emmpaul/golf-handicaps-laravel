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

import ImportShow from '@/pages/imports/show';
import type { ImportEditor, ImportSummary } from '@/pages/imports/show';
import {
    GAB,
    GAB_STROKES,
    HOLES,
    MANU,
    MANU_STROKES,
    PLAYERS,
} from '@/tests/reference-round';

// Spec scorecard-import « Correction manuelle d'un import », « Repère de
// lecture par l'IA », « Cases vides de la lecture surlignées dès
// l'ouverture », « Contenu lu sur la carte », « Indicateur
// d'enregistrement », « Enregistrement d'un import » and design D7: the page
// mounts the RoundEditor with the props of ImportController@show. The
// router's verbs and `fetch` are stubbed (jsdom cannot run a visit nor reach
// the server). Head renders nothing outside createInertiaApp; jsdom has no
// matchMedia nor ResizeObserver; the orb paints on a canvas jsdom cannot draw.
// Mutations:
// - the opening highlight applied to the empty screen
//   (`flagUntouchedEmpty` always true) → the failed import test counts
//   flagged cells and goes red;
// - the mark shown on the empty screen (`readByAi` always true) → the failed
//   import test goes red;
// - the round sent to RoundController.store → the saving test reads
//   `/rounds` and goes red;
// - the photo of the round (RoundPhotoController) → the photo test goes red.
// Spec scorecard-import « Mise en page de l'écran de vérification » and
// app-shell « Navigation masquée pendant l'édition » (task 6.3):
// - a « Recadrer la photo » link rendered → the no crop test goes red;
// - the photo without its 16/10 frame → the layout test goes red;
// - the mark painted `text-ink-2` → the layout test goes red;
// - the saving status left in `text-sm` → the layout test goes red;
// - `layout` dropped from the page → the chrome test goes red.

vi.mock('thinking-orbs', () => ({
    ThinkingOrb: () => <canvas data-testid="orb" />,
}));

const IMPORT = '0190a6b2-7c3e-7000-8000-0000000000c1';

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ url: `/imports/${IMPORT}`, props: {} }),
}));

const readyImport: ImportSummary = {
    id: IMPORT,
    status: 'ready',
    label: 'Prêt à vérifier',
    failure: null,
};

/** The reference card as read, Gab's hole 9 not read; the lines matched to Gab and Manu. */
const readCard = (gabHole9: number | null, playerIds = [GAB, MANU]) => ({
    courseName: 'BETHPAGE BLACK',
    tees: 'Red',
    playedOn: '2026-10-17',
    holes: HOLES,
    lines: [
        {
            playerId: playerIds[0],
            strokes: [
                ...GAB_STROKES.slice(0, 8),
                gabHole9,
                ...GAB_STROKES.slice(9),
            ],
            readName: 'GAB',
            totals: { out: 38, in: 34, total: 72 },
        },
        {
            playerId: playerIds[1],
            strokes: MANU_STROKES,
            readName: 'MANU',
            totals: { out: 46, in: 42, total: 88 },
        },
    ],
    parTotals: { out: 36, in: 35, total: 71 },
});

const fromReading: ImportEditor = {
    card: readCard(null),
    fromReading: true,
    readByAi: true,
};

const emptyCard = {
    courseName: null,
    tees: null,
    playedOn: '2026-10-28',
    holes: HOLES.map(() => ({ par: null, strokeIndex: null, distance: null })),
    lines: [{ playerId: null, strokes: Array(18).fill(null) }],
};

let post: MockInstance<typeof router.post>;
let fetchMock: MockInstance<typeof fetch>;

beforeEach(() => {
    vi.stubGlobal('matchMedia', () => ({
        matches: false,
        addEventListener: () => {},
        removeEventListener: () => {},
    }));
    fetchMock = vi.fn<typeof fetch>(
        async () => new Response(null, { status: 204 }),
    );
    vi.stubGlobal('fetch', fetchMock);
    document.cookie = 'XSRF-TOKEN=token%3D';
    window.history.replaceState({}, '', `/imports/${IMPORT}`);
    post = vi.spyOn(router, 'post').mockImplementation(() => {});
});

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

const cell = (name: string) =>
    screen.getByRole('textbox', { name }) as HTMLInputElement;

function page(item: ImportSummary, editor: ImportEditor) {
    return (
        <ImportShow
            import={item}
            editor={editor}
            players={PLAYERS}
            courses={[]}
        />
    );
}

describe('Correction screen of an import read by the AI', () => {
    it('shows the mark, the photo beside the grid and the card read', () => {
        render(page(readyImport, fromReading));

        expect(
            screen.getByText('Carte lue par l’IA, vérifie chaque case'),
        ).toHaveClass('text-info');
        expect(
            screen
                .getByRole('img', { name: 'Photo de la carte importée' })
                .getAttribute('src'),
        ).toBe(`/imports/${IMPORT}/photo`);
        expect(cell('Parcours').value).toBe('BETHPAGE BLACK');
        expect(cell('Tees').value).toBe('Red');
        expect(screen.getByRole('button', { name: 'Date' }).textContent).toBe(
            '17 octobre 2026',
        );
        expect(
            within(screen.getByRole('group', { name: 'Ligne 1' })).getByText(
                'lu « GAB »',
            ),
        ).toBeInTheDocument();
        expect(
            Array.from({ length: 18 }, (_, hole) =>
                Number(cell(`Par trou ${hole + 1}`).value),
            ),
        ).toEqual([4, 4, 3, 5, 4, 4, 5, 3, 4, 4, 4, 4, 5, 3, 4, 4, 3, 4]);
    });

    it('highlights the cell the reading left empty from the opening', () => {
        render(page(readyImport, fromReading));

        expect(cell('Ligne 1 trou 9').value).toBe('');
        expect(cell('Ligne 1 trou 9').getAttribute('aria-invalid')).toBe(
            'true',
        );
        expect(cell('Ligne 1 trou 8').getAttribute('aria-invalid')).toBeNull();
    });

    it('announces the autosave in a status region: « Enregistrement… » then « Enregistré »', async () => {
        render(page(readyImport, fromReading));

        const status = screen.getByTestId('autosave-status');
        expect(status.getAttribute('role')).toBe('status');
        expect(status.textContent).toBe('');

        await userEvent.type(cell('Ligne 1 trou 9'), '3');
        act(() => {
            window.dispatchEvent(new Event('pagehide'));
        });

        expect(fetchMock).toHaveBeenCalledTimes(1);
        const [url, init] = fetchMock.mock.calls[0];
        expect(url).toBe(`/imports/${IMPORT}/correction`);
        expect(init?.method).toBe('PUT');
        expect(init?.keepalive).toBe(true);
        expect(new Headers(init?.headers).get('X-XSRF-TOKEN')).toBe('token=');
        expect(JSON.parse(init?.body as string).card.lines[0].strokes[8]).toBe(
            3,
        );
        expect(status.textContent).toBe('Enregistrement…');

        await act(async () => {});

        expect(status.textContent).toBe('Enregistré');
    });

    it('saves the round through POST /imports/{id}/round with the names and totals read', async () => {
        render(
            page(readyImport, {
                card: readCard(5),
                fromReading: false,
                readByAi: true,
            }),
        );

        await userEvent.click(
            screen.getByRole('button', { name: 'Enregistrer' }),
        );

        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0][0]).toEqual({
            url: `/imports/${IMPORT}/round`,
            method: 'post',
        });
        expect(post.mock.calls[0][1]).toEqual({
            ...readCard(5),
            force: false,
        });
    });
});

describe('Empty correction screen of a failed import', () => {
    const failedImport: ImportSummary = {
        id: IMPORT,
        status: 'failed',
        label: 'Échec',
        failure: 'Lecture IA indisponible',
    };

    it('shows the photo and the failure, without mark nor flagged cell', () => {
        const { container } = render(
            page(failedImport, {
                card: emptyCard,
                fromReading: false,
                readByAi: false,
            }),
        );

        expect(
            screen.getByRole('img', { name: 'Photo de la carte importée' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Lecture IA indisponible')).toBeInTheDocument();
        expect(screen.queryByText(/Carte lue par l/)).toBeNull();
        expect(
            container.querySelectorAll('[aria-invalid="true"]'),
        ).toHaveLength(0);
        expect(screen.getByRole('button', { name: 'Date' }).textContent).toBe(
            '28 octobre 2026',
        );
        expect(
            screen.getAllByRole('combobox', { name: /^Joueur de la ligne/ }),
        ).toHaveLength(1);
    });
});

describe('Layout of the verification screen', () => {
    it('titles « Vérifier la carte » under the back link « Importer », the photo in its 16/10 frame below', () => {
        render(page(readyImport, fromReading));

        const topRow = document.querySelector(
            '[data-slot="page-top-row"]',
        ) as HTMLElement;
        const title = screen.getByRole('heading', { level: 1 });
        const photo = screen.getByRole('img', {
            name: 'Photo de la carte importée',
        });

        expect(within(topRow).getByRole('link').textContent).toBe('Importer');
        expect(within(topRow).getByRole('link').getAttribute('href')).toBe(
            '/import',
        );
        expect(title.textContent).toBe('Vérifier la carte');
        expect(
            title.compareDocumentPosition(photo) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
        expect(photo).toHaveClass('aspect-[16/10]', 'rounded-12');
        expect(screen.getByTestId('autosave-status')).toHaveClass(
            'font-mono',
            'text-ink-2',
        );
    });

    it('offers neither « Recadrer » nor « Recadrer la photo »', () => {
        render(page(readyImport, fromReading));

        expect(screen.queryByText(/Recadrer/)).toBeNull();
    });

    it('declares the editor chrome of the layout', () => {
        expect(ImportShow.layout).toEqual({ chrome: 'editor' });
    });
});
