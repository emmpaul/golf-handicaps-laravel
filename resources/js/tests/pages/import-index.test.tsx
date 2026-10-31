import { router } from '@inertiajs/react';
import type { VisitOptions } from '@inertiajs/core';
import type { MockInstance } from 'vite-plus/test';
import {
    act,
    fireEvent,
    render,
    screen,
    waitFor,
    within,
} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { toast } from 'sonner';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';

import { Toaster } from '@/components/ui/toaster';
import { list, remove as forget } from '@/lib/photo-queue';
import ImportIndex from '@/pages/import/index';
import '@/tests/indexeddb';
import type { ImportListItem } from '@/components/import/import-list';
import {
    advancePoll,
    expectReloadsEvery2s,
    spyOnPollReload,
} from '@/tests/poll';
import type { PollReload } from '@/tests/poll';
import { ROUND } from '@/tests/reference-round';
import { expectPrefetchedOnTouchAndHover } from '@/tests/list-link-prefetch';

// Spec scorecard-import « Envoi d'une photo », « Prise de photo depuis
// l'app » (with « Prise de vue annulée »), « Une action principale à l'import », « Lecture en tâche de fond »,
// « Liste des imports », « Relance refusée », « Suppression d'un import »;
// scorecard-ai-completion « Lecture désactivée sans clé d'API »;
// loading-feedback « Attentes de l'envoi d'une photo », « Imports en attente
// de lecture », « Attentes de la suppression d'un import », « Attente de la
// relance d'une lecture ». The photo decoding is simulated (jsdom decodes no
// image) and resolved or rejected by each test. The router's verbs are
// stubbed (jsdom cannot run a visit); the visit callbacks they receive are
// played by hand. The poll of Inertia runs on fake timers; its reload goes
// through the router's `doReload`, stubbed to record the request options
// (`tests/poll.ts`). The photo goes through the real queue of the device
// (fake-indexeddb, emptied before each test) and leaves by `fetch`, held or
// answered by each test (design D7, offline-pwa); `navigator.onLine` is
// stubbed. jsdom has no object URL: thumbnails get `blob:photo`.
// Mutations of offline-pwa:
// - the offline message replaced by the toast of a send (« Lecture en file
//   d'attente… ») → the offline tests go red;
// - « Envoi de la photo… » shown before checking the network → the offline
//   test goes red;
// - the photo put in the queue before its decoding succeeded → the
//   unreadable test goes red;
// - the tiles left disabled after a failed send → the « fails on the way »
//   test goes red.
// Head renders nothing outside createInertiaApp; the orb paints on a canvas
// jsdom cannot draw.
// Mutations:
// - the poll never stopped (no `stop()` once nothing waits) → the poll test
//   records reloads after the import is ready and goes red;
// - the orb shown for `ready` too → the orb test counts it and goes red;
// - « Relancer la lecture » shown for every failed import, without looking at
//   the actions (no key) → the « without key » test goes red;
// - « Prendre une photo » rendered without a coarse pointer → the computer
//   test goes red;
// - the buttons left enabled while preparing → the pending test goes red;
// - « Relancer la lecture » left enabled while its reading is retried → the
//   retry test goes red;
// - « Annuler » wired to the delete visit → the cancel test goes red;
// - « Vérifier » and « Supprimer » rendered for every line whatever its
//   actions (buttons for an import being read) → the list test expects no
//   action on the reading line and goes red;
// - a « Recadrer » button rendered beside « Vérifier » → the ready line test
//   goes red.
// Capitals of the design go through `uppercase` (design D2): the DOM keeps
// « Imports », « Caméra », « Échec »; the words the design changes
// (« À VÉRIFIER », « LECTURE… ») are written as such, and so is
// « ENREGISTRÉ », the pill of a saved import (DO28).

vi.mock('thinking-orbs', () => ({
    ThinkingOrb: () => <canvas data-testid="orb" />,
}));

const decoding = vi.hoisted(() => ({
    resolve: (_blob: Blob) => {},
    reject: (_error: Error) => {},
}));

vi.mock('@/components/import/decode-photo', () => ({
    decodePhoto: () =>
        new Promise<Blob>((resolve, reject) => {
            decoding.resolve = resolve;
            decoding.reject = reject;
        }),
}));

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ url: '/import', props: {} }),
}));

const READY = '0190a6b2-7c3e-7000-8000-0000000000c1';
const FAILED = '0190a6b2-7c3e-7000-8000-0000000000c2';
const QUEUED = '0190a6b2-7c3e-7000-8000-0000000000c3';
const READING = '0190a6b2-7c3e-7000-8000-0000000000c4';
const SAVED = '0190a6b2-7c3e-7000-8000-0000000000c5';

const ready: ImportListItem = {
    id: READY,
    status: 'ready',
    label: 'Prêt à vérifier',
    failure: null,
    roundId: null,
    createdAt: '2026-10-17 21:05',
    actions: ['review', 'delete'],
};
const failed: ImportListItem = {
    id: FAILED,
    status: 'failed',
    label: 'Échec',
    failure: 'Budget IA du mois atteint',
    roundId: null,
    createdAt: '2026-10-17 21:04',
    actions: ['correctEmpty', 'retry', 'delete'],
};
const queued: ImportListItem = {
    id: QUEUED,
    status: 'queued',
    label: 'En attente',
    failure: null,
    roundId: null,
    createdAt: '2026-10-17 21:03',
    actions: ['delete'],
};
const reading: ImportListItem = {
    id: READING,
    status: 'reading',
    label: 'Lecture en cours',
    failure: null,
    roundId: null,
    createdAt: '2026-10-17 21:02',
    actions: [],
};
const saved: ImportListItem = {
    id: SAVED,
    status: 'ready',
    label: 'Prêt à vérifier',
    failure: null,
    roundId: ROUND,
    createdAt: '2026-10-17 21:01',
    actions: ['viewRound'],
};

const UPLOAD_KEY = '7f3c1a52-0d4e-4b8a-9c61-2e5f8a9b0c11';
const QUEUED_MESSAGE =
    "Lecture en file d'attente. Tu peux envoyer la photo suivante.";
const KEPT_MESSAGE =
    'Photo gardée sur le téléphone. Elle partira au retour du réseau.';
const UNSAVED_MESSAGE = 'Photo non gardée sur le téléphone. Réessaie.';

const fetchMock = vi.fn<typeof fetch>();
let online = true;
let coarse = false;
let post: MockInstance<typeof router.post>;
let remove: MockInstance<typeof router.delete>;
let reload: PollReload;

beforeEach(async () => {
    for (const photo of await list()) {
        await forget(photo.key);
    }

    online = true;
    vi.spyOn(navigator, 'onLine', 'get').mockImplementation(() => online);
    vi.spyOn(crypto, 'randomUUID').mockReturnValue(UPLOAD_KEY);
    URL.createObjectURL = vi.fn(() => 'blob:photo');
    URL.revokeObjectURL = vi.fn();
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    coarse = false;
    vi.stubGlobal('matchMedia', (query: string) => ({
        matches: coarse && query === '(pointer: coarse)',
        media: query,
        addEventListener: () => {},
        removeEventListener: () => {},
    }));
    post = vi.spyOn(router, 'post').mockImplementation(() => {});
    remove = vi.spyOn(router, 'delete').mockImplementation(() => {});
    reload = spyOnPollReload();
});

afterEach(() => {
    act(() => {
        toast.dismiss();
    });
    vi.useRealTimers();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

const photoInput = () => screen.getByLabelText('Photo de la carte');
const status = () => screen.getAllByRole('status')[0].textContent;

/** Every text the waiting region of the upload shows from now on, in order. */
function recordStatus(): string[] {
    const region = screen.getAllByRole('status')[0];
    const texts: string[] = [];

    new MutationObserver(() => {
        const text = region.textContent ?? '';

        if (texts.at(-1) !== text) {
            texts.push(text);
        }
    }).observe(region, { childList: true, subtree: true, characterData: true });

    return texts;
}

/** Chooses a HEIC photo and lets its decoding succeed. */
async function choosePhoto(): Promise<void> {
    await userEvent.upload(
        photoInput(),
        new File(['photo'], 'IMG_3064.HEIC', { type: 'image/heic' }),
    );
    await act(async () => {
        decoding.resolve(new Blob(['jpeg'], { type: 'image/jpeg' }));
    });
}

/** The chosen photo waits on the device, « Photo gardée… » shown, the tiles enabled again. */
async function expectPhotoKept(): Promise<void> {
    expect(await screen.findByText(KEPT_MESSAGE)).toBeInTheDocument();
    expect(screen.queryByText(QUEUED_MESSAGE)).toBeNull();
    expect(status()).toBe('');
    expect(photoInput()).not.toBeDisabled();
    expect(screen.getByLabelText('Prendre une photo')).not.toBeDisabled();
    expect((await list()).map((kept) => kept.key)).toEqual([UPLOAD_KEY]);
}

/** Lets the toasts asked for so far show. */
async function settleToasts(): Promise<void> {
    await act(async () => {
        await new Promise((resolve) => setTimeout(resolve, 50));
    });
}

/** The line of the import shown with `heading`. */
const line = (heading: string) =>
    screen
        .getByRole('heading', { level: 3, name: heading })
        .closest('li') as HTMLElement;

/** The names of the actions of a line, links and buttons, in their order (the trash is named by `aria-label`). */
const actions = (item: HTMLElement) =>
    [...item.querySelectorAll('a, button')].map(
        (action) => action.getAttribute('aria-label') ?? action.textContent,
    );

describe('Sending a photo', () => {
    // Spec « Une action principale à l'import », « Mise en page de l'écran
    // Importer ». Mutations: « Prendre une photo » rendered under « Choisir une
    // photo » instead of beside it (not the same group) → red; the captions
    // « CAMÉRA » / « PHOTOTHÈQUE » dropped → red.
    it('on a touch screen, puts the tiles « Prendre une photo » / « CAMÉRA » and « Choisir une photo » / « PHOTOTHÈQUE » side by side', () => {
        coarse = true;

        render(<ImportIndex imports={[]} readingEnabled />);

        const take = screen.getByLabelText('Prendre une photo');
        const takeTile = take.closest('label') as HTMLElement;
        const chooseTile = photoInput().closest('label') as HTMLElement;
        const group = takeTile.parentElement as HTMLElement;

        expect(take.getAttribute('capture')).toBe('environment');
        expect(takeTile.textContent).toBe('Prendre une photoCaméra');
        expect(chooseTile.textContent).toBe('Choisir une photoPhotothèque');
        expect(takeTile.nextElementSibling).toBe(chooseTile);
        expect(group.children).toHaveLength(2);
        expect(group.className).toContain('rounded-14');
        expect(takeTile.className).toContain('flex-1');
        expect(chooseTile.className).toContain('flex-1');
        expect(takeTile.className).toContain('bg-surface-1');
        expect(screen.queryByText('Photo de la carte')).toBeNull();
    });

    // Spec « Prise de vue annulée ». The camera closed without a photo fires a
    // change with no file. Mutation: the empty selection not ignored (`if
    // (!file) return` removed in import-upload.tsx) → the empty choice goes to
    // the decoding, « Préparation de la photo… » shows and the buttons are
    // disabled: the test goes red.
    it('leaves the screen as it was when the camera is closed without a photo', () => {
        coarse = true;
        render(<ImportIndex imports={[]} readingEnabled />);

        fireEvent.change(screen.getByLabelText('Prendre une photo'), {
            target: { files: [] },
        });

        expect(fetchMock).not.toHaveBeenCalled();
        expect(status()).toBe('');
        expect(screen.queryByRole('alert')).toBeNull();
        expect(
            screen.queryByText(
                'Format de photo non lisible, exporte-la en JPEG',
            ),
        ).toBeNull();
        expect(screen.getByLabelText('Prendre une photo')).not.toBeDisabled();
        expect(photoInput()).not.toBeDisabled();
    });

    it('on a computer, shows the single tile « Choisir une photo » and keeps « Photo de la carte »', () => {
        render(<ImportIndex imports={[]} readingEnabled />);

        const chooseTile = photoInput().closest('label') as HTMLElement;

        expect(screen.queryByLabelText('Prendre une photo')).toBeNull();
        expect(screen.queryByText('Caméra')).toBeNull();
        expect(photoInput().getAttribute('type')).toBe('file');
        expect(chooseTile.parentElement?.children).toHaveLength(1);
        expect(chooseTile.className).toContain('flex-1');
    });

    // Spec « Mise en page de l'écran Importer ».
    it('shows the title, « Saisie manuelle », the hint and « NOUVELLE CARTE »', () => {
        render(<ImportIndex imports={[]} readingEnabled />);

        expect(screen.getByRole('heading', { level: 1 }).textContent).toBe(
            'Importer',
        );
        expect(
            screen
                .getByRole('link', { name: 'Saisie manuelle' })
                .getAttribute('href'),
        ).toBe('/rounds/new');
        expect(
            screen.getByText(
                "Photo de l'écran de fin de round, prise au téléphone.",
            ),
        ).toBeInTheDocument();
        expect(screen.getByText('Nouvelle carte')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Golf' }).getAttribute('href'),
        ).toBe('/');
    });

    it('shows « Préparation de la photo… » then « Envoi de la photo… », the buttons disabled, then « Lecture en file d’attente… »', async () => {
        coarse = true;
        render(
            <>
                <ImportIndex imports={[]} readingEnabled />
                <Toaster />
            </>,
        );
        let answer: (response: Response) => void = () => {};
        fetchMock.mockImplementationOnce(
            () =>
                new Promise<Response>((resolve) => {
                    answer = resolve;
                }),
        );

        await userEvent.upload(
            photoInput(),
            new File(['photo'], 'IMG_3064.HEIC', { type: 'image/heic' }),
        );

        expect(status()).toBe('Préparation de la photo…');
        expect(photoInput()).toBeDisabled();
        expect(screen.getByLabelText('Prendre une photo')).toBeDisabled();
        expect(fetchMock).not.toHaveBeenCalled();

        await act(async () => {
            decoding.resolve(new Blob(['jpeg'], { type: 'image/jpeg' }));
        });

        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1));
        expect(status()).toBe('Envoi de la photo…');
        expect(photoInput()).toBeDisabled();
        const [url, init] = fetchMock.mock.calls[0];
        const body = (init as RequestInit).body as FormData;
        const photo = body.get('photo') as File;
        expect(url).toBe('/imports');
        expect([photo.name, photo.type]).toEqual(['carte.jpg', 'image/jpeg']);
        expect(body.get('upload_key')).toBe(UPLOAD_KEY);
        expect(post).not.toHaveBeenCalled();
        expect(
            screen.queryByRole('heading', { name: "En attente d'envoi" }),
        ).toBeNull();

        await act(async () => {
            answer(new Response('{"id":"a"}', { status: 201 }));
        });

        await waitFor(() => expect(status()).toBe(''));
        expect(photoInput()).not.toBeDisabled();
        expect(await screen.findByText(QUEUED_MESSAGE)).toBeInTheDocument();
        expect(await list()).toEqual([]);
    });

    // Spec scorecard-import « Photo gardée hors ligne », loading-feedback
    // « Photo gardée hors ligne ».
    it('offline, keeps the photo on the device with « Photo gardée sur le téléphone… », without « Envoi de la photo… »', async () => {
        coarse = true;
        online = false;
        render(
            <>
                <ImportIndex imports={[]} readingEnabled />
                <Toaster />
            </>,
        );

        const shown = recordStatus();

        await choosePhoto();

        await expectPhotoKept();
        expect(shown).toEqual(['Préparation de la photo…', '']);
        expect(fetchMock).not.toHaveBeenCalled();
        expect(
            await screen.findByRole('heading', { name: "En attente d'envoi" }),
        ).toBeVisible();
    });

    it('keeps the photo when its send fails on the way, the tiles enabled again', async () => {
        coarse = true;
        fetchMock.mockRejectedValueOnce(new TypeError('Failed to fetch'));
        render(
            <>
                <ImportIndex imports={[]} readingEnabled />
                <Toaster />
            </>,
        );

        await choosePhoto();

        await expectPhotoKept();
    });

    // Spec scorecard-import « Reprise de l'envoi après la saisie du code ».
    // Mutation: the fate of a send stopped by a 401 left at « kept »
    // (`'login'` not returned by `uploadPhoto`) → « Photo gardée sur le
    // téléphone… » shows while /login opens: the test goes red.
    it('on a 401, opens /login without « Photo gardée sur le téléphone… » and keeps the photo', async () => {
        coarse = true;
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        fetchMock.mockResolvedValueOnce(
            new Response(JSON.stringify({ error: 'Session requise' }), {
                status: 401,
                headers: { 'Content-Type': 'application/json' },
            }),
        );
        render(
            <>
                <ImportIndex imports={[]} readingEnabled />
                <Toaster />
            </>,
        );

        await choosePhoto();

        await waitFor(() => expect(photoInput()).not.toBeDisabled());
        await settleToasts();
        expect(visit.mock.calls.map(([url]) => url)).toEqual(['/login']);
        expect(screen.queryByText(KEPT_MESSAGE)).toBeNull();
        expect(screen.queryByText(QUEUED_MESSAGE)).toBeNull();
        expect(status()).toBe('');
        expect((await list()).map((kept) => kept.key)).toEqual([UPLOAD_KEY]);
    });

    // Mutation: the failure of IndexedDB not caught in `uploadPhoto` → the
    // rejection escapes `choose`, no message shows: the test goes red.
    it('says « Photo non gardée sur le téléphone. Réessaie. » when the device cannot keep the photo, and sends nothing', async () => {
        coarse = true;
        vi.spyOn(IDBObjectStore.prototype, 'add').mockImplementation(() => {
            throw new DOMException('Quota exceeded', 'QuotaExceededError');
        });
        render(
            <>
                <ImportIndex imports={[]} readingEnabled />
                <Toaster />
            </>,
        );

        await choosePhoto();

        expect((await screen.findByRole('alert')).textContent).toBe(
            UNSAVED_MESSAGE,
        );
        expect(screen.queryByText(KEPT_MESSAGE)).toBeNull();
        expect(status()).toBe('');
        expect(fetchMock).not.toHaveBeenCalled();
        expect(photoInput()).not.toBeDisabled();
        expect(screen.getByLabelText('Prendre une photo')).not.toBeDisabled();
        expect(await list()).toEqual([]);
    });

    it('shows « Format de photo non lisible, exporte-la en JPEG » and « Saisie manuelle » for a file it cannot decode', async () => {
        render(<ImportIndex imports={[]} readingEnabled />);

        await userEvent.upload(
            photoInput(),
            new File(['not an image'], 'notes.pdf', { type: 'image/png' }),
        );
        await act(async () => {
            decoding.reject(
                new Error('The source image could not be decoded.'),
            );
        });

        expect(
            screen.getByText('Format de photo non lisible, exporte-la en JPEG'),
        ).toBeInTheDocument();
        expect(
            within(screen.getByRole('alert'))
                .getByRole('link', { name: 'Saisie manuelle' })
                .getAttribute('href'),
        ).toBe('/rounds/new');
        expect(fetchMock).not.toHaveBeenCalled();
        expect(await list()).toEqual([]);
        expect(photoInput()).not.toBeDisabled();
    });

    // Mutation: the refusal of the server not shown → no alert, the test goes
    // red.
    it('shows the refusal of the server under the buttons: « La photo dépasse 20 Mo », with « Saisie manuelle »', async () => {
        fetchMock.mockResolvedValueOnce(
            new Response(
                JSON.stringify({
                    message: 'La photo dépasse 20 Mo',
                    errors: { photo: ['La photo dépasse 20 Mo'] },
                }),
                { status: 422 },
            ),
        );
        render(<ImportIndex imports={[]} readingEnabled />);

        await userEvent.upload(
            photoInput(),
            new File(['photo'], 'IMG_3064.JPG', { type: 'image/jpeg' }),
        );
        await act(async () => {
            decoding.resolve(new Blob(['jpeg'], { type: 'image/jpeg' }));
        });

        const refusal = await screen.findByRole('alert');
        expect(
            within(refusal).getByText('La photo dépasse 20 Mo'),
        ).toBeVisible();
        expect(
            within(refusal)
                .getByRole('link', { name: 'Saisie manuelle' })
                .getAttribute('href'),
        ).toBe('/rounds/new');
        await waitFor(() => expect(photoInput()).not.toBeDisabled());
        expect(await list()).toEqual([]);
    });

    it('without key, shows the reading disabled and « Saisie manuelle », without any sending action', () => {
        coarse = true;

        render(<ImportIndex imports={[failed]} readingEnabled={false} />);

        expect(
            screen.getByText(
                'Lecture des photos désactivée : aucune clé d’IA n’est configurée.',
            ),
        ).toBeInTheDocument();
        expect(
            within(screen.getByRole('alert'))
                .getByRole('link', { name: 'Saisie manuelle' })
                .getAttribute('href'),
        ).toBe('/rounds/new');
        expect(screen.queryByLabelText('Prendre une photo')).toBeNull();
        expect(screen.queryByLabelText('Photo de la carte')).toBeNull();
        expect(screen.queryByText('Choisir une photo')).toBeNull();
    });
});

describe('List of imports', () => {
    it('shows each import with its date, its state and the actions of its state', () => {
        render(
            <ImportIndex
                imports={[ready, failed, queued, reading, saved]}
                readingEnabled
            />,
        );

        const list = screen.getByRole('list', { name: 'Imports' });
        expect(
            within(list)
                .getAllByRole('heading', { level: 3 })
                .map((heading) => heading.textContent),
        ).toEqual([
            '17 oct. 2026 · 21:05',
            '17 oct. 2026 · 21:04',
            '17 oct. 2026 · 21:03',
            '17 oct. 2026 · 21:02',
            '17 oct. 2026 · 21:01',
        ]);
        expect(
            within(list)
                .getAllByTestId('import-status')
                .map((badge) => badge.textContent),
        ).toEqual([
            'À VÉRIFIER',
            'Échec',
            'En attente',
            'LECTURE…',
            'ENREGISTRÉ',
        ]);

        expect(actions(line('17 oct. 2026 · 21:05'))).toEqual([
            'Vérifier',
            'Supprimer',
        ]);
        expect(actions(line('17 oct. 2026 · 21:04'))).toEqual([
            "Ouvrir l'écran de correction vide",
            'Relancer la lecture',
            'Supprimer',
        ]);
        expect(actions(line('17 oct. 2026 · 21:03'))).toEqual(['Supprimer']);
        expect(actions(line('17 oct. 2026 · 21:02'))).toEqual([]);
        expect(actions(line('17 oct. 2026 · 21:01'))).toEqual([
            'Voir le round',
        ]);

        expect(
            within(line('17 oct. 2026 · 21:05'))
                .getByRole('link', { name: 'Vérifier' })
                .getAttribute('href'),
        ).toBe(`/imports/${READY}`);
        expect(
            within(line('17 oct. 2026 · 21:04'))
                .getByRole('link', {
                    name: "Ouvrir l'écran de correction vide",
                })
                .getAttribute('href'),
        ).toBe(`/imports/${FAILED}`);
        expect(
            within(line('17 oct. 2026 · 21:01'))
                .getByRole('link', { name: 'Voir le round' })
                .getAttribute('href'),
        ).toBe(`/rounds/${ROUND}`);
        expect(
            within(line('17 oct. 2026 · 21:04')).getByText(
                'Budget IA du mois atteint',
            ),
        ).toBeInTheDocument();
    });

    // Spec « Ligne d'un import ». Mutations: the ready pill left neutral
    // (`secondary`) → red; the reading pill in `accent` → red; the failed pill
    // without `text-signal` → red; the saved import shown « Prêt à vérifier »
    // in amber like a ready one (its status stays `ready`, DO28) → red.
    it('colours the pills: « À VÉRIFIER » amber, « LECTURE… » cyan, « ÉCHEC » red, « En attente » and « ENREGISTRÉ » neutral', () => {
        render(
            <ImportIndex
                imports={[ready, failed, queued, reading, saved]}
                readingEnabled
            />,
        );

        const pill = (heading: string) =>
            within(line(heading)).getByTestId('import-status').className;

        expect(pill('17 oct. 2026 · 21:05')).toContain('text-accent');
        expect(pill('17 oct. 2026 · 21:05')).toContain('bg-accent/15');
        expect(pill('17 oct. 2026 · 21:04')).toContain('text-signal');
        expect(pill('17 oct. 2026 · 21:03')).toContain('text-ink-2');
        expect(pill('17 oct. 2026 · 21:02')).toContain('text-info');
        expect(pill('17 oct. 2026 · 21:02')).toContain('bg-info/15');
        expect(pill('17 oct. 2026 · 21:01')).toContain('text-ink-2');
        expect(pill('17 oct. 2026 · 21:01')).not.toContain('text-accent');
    });

    // Mutations: the date written « 23 octobre 2026 à 16:58 » (the former
    // heading) → red; the thumbnail dropped → red.
    it('titles a line with the instant of sending, « 23 oct. 2026 · 16:58 », beside the thumbnail of its photo', () => {
        render(
            <ImportIndex
                imports={[{ ...ready, createdAt: '2026-10-23 16:58' }]}
                readingEnabled
            />,
        );

        const item = line('23 oct. 2026 · 16:58');
        const thumbnail = item.querySelector('img') as HTMLImageElement;

        expect(thumbnail.getAttribute('src')).toBe(`/imports/${READY}/photo`);
        expect(thumbnail.getAttribute('alt')).toBe('');
        expect(thumbnail.parentElement?.className).toContain('h-[42px]');
        expect(thumbnail.parentElement?.className).toContain('w-14');
        expect(within(item).getByRole('time').getAttribute('datetime')).toBe(
            '2026-10-23T16:58',
        );
    });

    // Mutations: « Vérifier » in an outline button → red; « Supprimer » kept as
    // a text button (no trash icon in `text-signal`) → red; a « Recadrer »
    // button rendered → red.
    it('gives a ready import the filled « Vérifier » and the square trash « Supprimer », never « Recadrer »', () => {
        render(<ImportIndex imports={[ready]} readingEnabled />);

        const item = line('17 oct. 2026 · 21:05');
        const verify = within(item).getByRole('link', { name: 'Vérifier' });
        const trash = within(item).getByRole('button', { name: 'Supprimer' });

        expect(verify.className).toContain('bg-ink');
        expect(verify.className).toContain('h-11');
        expect(verify.className).toContain('flex-1');
        expect(trash.textContent).toBe('');
        expect(trash.className).toContain('size-11');
        expect(trash.className).toContain('border-rule');
        expect(trash.querySelector('svg')?.getAttribute('class')).toContain(
            'text-signal',
        );
        expect(screen.queryByText(/Recadrer/)).toBeNull();
    });

    it('writes the failure of a failed import in grey 13 px under its line', () => {
        render(<ImportIndex imports={[failed]} readingEnabled />);

        const reason = within(line('17 oct. 2026 · 21:04')).getByText(
            'Budget IA du mois atteint',
        );

        expect(reason.className).toContain('text-body-13');
        expect(reason.className).toContain('text-ink-2');
    });

    // Spec « Mise en page de l'écran Importer ». Mutation: the section hidden
    // when the list is empty (the former `return null`) → red.
    it('heads the list with « IMPORTS » and its count, and says « Aucun import en attente. » when empty', () => {
        const { rerender } = render(
            <ImportIndex imports={[ready, failed]} readingEnabled />,
        );

        const heading = screen.getByRole('heading', {
            level: 2,
            name: 'Imports',
        });
        expect(heading.parentElement?.textContent).toBe('Imports2');

        rerender(<ImportIndex imports={[]} readingEnabled />);

        expect(
            screen.getByRole('heading', { level: 2, name: 'Imports' })
                .parentElement?.textContent,
        ).toBe('Imports0');
        expect(
            screen.getByText('Aucun import en attente.'),
        ).toBeInTheDocument();
    });

    it('shows the animated orb next to the queued and reading imports only', () => {
        render(
            <ImportIndex
                imports={[ready, failed, queued, reading]}
                readingEnabled
            />,
        );

        const withOrb = within(screen.getByRole('list', { name: 'Imports' }))
            .getAllByRole('listitem')
            .map((item) => within(item).queryAllByTestId('orb').length);

        expect(withOrb).toEqual([0, 0, 1, 1]);
    });

    it('does not offer « Relancer la lecture » without key', () => {
        render(
            <ImportIndex
                imports={[{ ...failed, actions: ['correctEmpty', 'delete'] }]}
                readingEnabled={false}
            />,
        );

        expect(actions(line('17 oct. 2026 · 21:04'))).toEqual([
            "Ouvrir l'écran de correction vide",
            'Supprimer',
        ]);
    });

    it('reads a failed import again: POST to its reading, « Relance de la lecture… » and its actions disabled on its line only', async () => {
        const other = { ...failed, id: QUEUED, createdAt: '2026-10-17 21:00' };

        render(<ImportIndex imports={[failed, other]} readingEnabled />);

        const retried = line('17 oct. 2026 · 21:04');
        await userEvent.click(
            within(retried).getByRole('button', {
                name: 'Relancer la lecture',
            }),
        );

        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0][0]).toEqual({
            url: `/imports/${FAILED}/reading`,
            method: 'post',
        });

        act(() => post.mock.calls[0][2]?.onStart?.({} as never));

        expect(within(retried).getByRole('status').textContent).toBe(
            'Relance de la lecture…',
        );
        expect(
            within(retried).getByRole('button', {
                name: 'Relancer la lecture',
            }),
        ).toBeDisabled();
        expect(
            within(retried).getByRole('button', { name: 'Supprimer' }),
        ).toBeDisabled();
        expect(
            within(retried)
                .getByRole('link', {
                    name: "Ouvrir l'écran de correction vide",
                })
                .getAttribute('aria-disabled'),
        ).toBe('true');

        const untouched = line('17 oct. 2026 · 21:00');
        expect(within(untouched).getByRole('status').textContent).toBe('');
        expect(
            within(untouched).getByRole('button', {
                name: 'Relancer la lecture',
            }),
        ).not.toBeDisabled();
        expect(
            within(untouched)
                .getByRole('link', {
                    name: "Ouvrir l'écran de correction vide",
                })
                .getAttribute('aria-disabled'),
        ).toBeNull();

        act(() => post.mock.calls[0][2]?.onFinish?.({} as never));
        expect(within(retried).getByRole('status').textContent).toBe('');
    });

    // M3, DO35: the badge of Importer follows a reading that ends.
    // Mutation: `pendingImports` left out of `only` → red.
    it('reloads the imports and their count every 2 s while one waits, and stops once none waits', async () => {
        vi.useFakeTimers();

        const { rerender } = render(
            <ImportIndex imports={[queued, ready]} readingEnabled />,
        );

        await expectReloadsEvery2s(reload, ['imports', 'pendingImports']);

        rerender(
            <ImportIndex
                imports={[
                    { ...queued, status: 'ready', label: 'Prêt à vérifier' },
                    ready,
                ]}
                readingEnabled
            />,
        );
        await advancePoll(10_000);
        expect(reload).toHaveBeenCalledTimes(2);
    });

    it('does not reload when no import waits', async () => {
        vi.useFakeTimers();

        render(<ImportIndex imports={[ready, failed]} readingEnabled />);

        await advancePoll(10_000);
        expect(reload).not.toHaveBeenCalled();
    });

    it('deletes an import after a confirmation, « Suppression de l’import… » while deleting', async () => {
        remove.mockImplementation((_url, options) => {
            (options as VisitOptions).onStart?.({} as never);
        });

        render(<ImportIndex imports={[ready]} readingEnabled />);

        await userEvent.click(
            within(line('17 oct. 2026 · 21:05')).getByRole('button', {
                name: 'Supprimer',
            }),
        );

        const dialog = screen.getByRole('alertdialog');
        const confirm = within(dialog).getByRole('button', {
            name: 'Supprimer',
        });
        expect(within(dialog).getByRole('heading').textContent).toBe(
            'Supprimer cet import ?',
        );
        expect(remove).not.toHaveBeenCalled();

        await act(async () => {
            await userEvent.click(confirm);
        });

        expect(remove).toHaveBeenCalledTimes(1);
        expect(remove.mock.calls[0][0]).toBe(`/imports/${READY}`);
        expect(within(dialog).getByRole('status').textContent).toBe(
            'Suppression de l’import…',
        );
        expect(
            within(dialog).getByRole('button', { name: 'Supprimer' }),
        ).toBeDisabled();
        expect(
            within(dialog).getByRole('button', { name: 'Annuler' }),
        ).toBeDisabled();
    });

    // B3, DO33: the sheet of an import opens on « Annuler » too (here
    // « Annuler » also comes first). Mutation: the initial focus of
    // AlertDialogContent aimed at the action
    // (`[data-slot="alert-dialog-action"]`) → this test goes red.
    it('opens the deletion sheet with the focus on « Annuler »', async () => {
        render(<ImportIndex imports={[ready]} readingEnabled />);

        await userEvent.click(
            within(line('17 oct. 2026 · 21:05')).getByRole('button', {
                name: 'Supprimer',
            }),
        );

        const dialog = screen.getByRole('alertdialog');

        await waitFor(() => {
            expect(document.activeElement).toBe(
                within(dialog).getByRole('button', { name: 'Annuler' }),
            );
        });
    });

    it('sends nothing when the deletion is cancelled', async () => {
        render(<ImportIndex imports={[ready]} readingEnabled />);

        await userEvent.click(
            within(line('17 oct. 2026 · 21:05')).getByRole('button', {
                name: 'Supprimer',
            }),
        );
        await userEvent.click(
            within(screen.getByRole('alertdialog')).getByRole('button', {
                name: 'Annuler',
            }),
        );

        expect(remove).not.toHaveBeenCalled();
        expect(line('17 oct. 2026 · 21:05')).toBeInTheDocument();
    });
});

// Spec app-shell « Liens de liste préchargés » (design D5): the links that open
// an import (« Vérifier », « Ouvrir l'écran de correction vide ») and the round
// of a saved import prefetch their page on touch, before the release, and on
// hover.
// Mutations: the `listLinkPrefetch` props dropped from `ActionLink` → no call
// on touch nor on hover, red; `onTouchStart` dropped → no call on touch, red.
describe('Import list links prefetch', () => {
    it.each([
        [
            '17 oct. 2026 · 21:05',
            'Vérifier',
            '/imports/0190a6b2-7c3e-7000-8000-0000000000c1',
        ],
        [
            '17 oct. 2026 · 21:04',
            "Ouvrir l'écran de correction vide",
            '/imports/0190a6b2-7c3e-7000-8000-0000000000c2',
        ],
        [
            '17 oct. 2026 · 21:01',
            'Voir le round',
            '/rounds/0190a6b2-7c3e-7000-8000-0000000000aa',
        ],
    ])(
        'prefetches the link of %s « %s » (%s) on touch and on hover',
        async (heading, name, href) => {
            render(
                <ImportIndex imports={[ready, failed, saved]} readingEnabled />,
            );

            await expectPrefetchedOnTouchAndHover(
                within(line(heading)).getByRole('link', { name }),
                href,
            );
        },
    );
});
