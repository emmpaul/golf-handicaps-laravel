import { act, render, screen, waitFor, within } from '@testing-library/react';
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
import { resetIndexedDb } from '@/tests/indexeddb';

// Spec scorecard-import « Liste des photos en attente d'envoi », « Envoi au
// retour du réseau », « Photo refusée à l'envoi »; loading-feedback « Envoi
// des photos en attente signalé ». The queue is the real one over
// fake-indexeddb, and the sends go through the real `photo-sync`; `fetch` is
// held or answered by each test. jsdom has no object URL: each thumbnail gets
// `blob:<content of the photo>`. Tests run under TZ=America/Montreal: the
// photos taken at 01:04 and 01:06 UTC on the 31st read 21:04 and 21:06 on
// the 30th. Each test loads fresh modules, as a page load would.
// Mutations:
// - list sorted from the newest to the oldest → the order test goes red;
// - section kept when no photo waits → the « absent » test goes red;
// - « Retirer » left enabled on the photo being sent → the flush test goes red;
// - « Retirer » wired to nothing → the « Retirer » test goes red;
// - « Envoi de N photos… » always plural → the one-photo test goes red;
// - the refusal shown without « Saisie manuelle » → the 422 test goes red;
// - the photo sent by the upload tiles listed too → the « uploading » test
//   goes red.

vi.mock('thinking-orbs', () => ({
    ThinkingOrb: () => <canvas data-testid="orb" />,
}));

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    router: { reload: vi.fn(), visit: vi.fn(), on: () => () => {} },
}));

type Queue = typeof import('@/lib/photo-queue');
type Sync = typeof import('@/lib/photo-sync');

const K1 = '7f3c1a52-0d4e-4b8a-9c61-2e5f8a9b0c11';
const K2 = '1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d';
const T1 = '2026-10-31T01:04:00.000Z';
const T2 = '2026-10-31T01:06:00.000Z';
const DISABLED =
    'Lecture des photos désactivée : aucune clé d’IA n’est configurée.';

const fetchMock = vi.fn<typeof fetch>();

async function load(): Promise<{ queue: Queue; sync: Sync }> {
    vi.resetModules();

    return {
        queue: await import('@/lib/photo-queue'),
        sync: await import('@/lib/photo-sync'),
    };
}

/** Renders the section from the modules `load` just gave. */
async function show() {
    const { PendingPhotos } =
        await import('@/components/import/pending-photos');

    render(
        <>
            <PendingPhotos />
            <Toaster />
        </>,
    );
}

async function enqueueAt(queue: Queue, at: string, content: string) {
    vi.setSystemTime(new Date(at));

    return queue.enqueue(new Blob([content], { type: 'image/jpeg' }));
}

function json(status: number, body: unknown): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

function deferred<T>() {
    let resolve!: (value: T) => void;
    const promise = new Promise<T>((done) => {
        resolve = done;
    });

    return { promise, resolve };
}

const section = () =>
    screen.queryByRole('heading', { level: 2, name: "En attente d'envoi" });

const items = () =>
    within(
        screen.getByRole('list', { name: "En attente d'envoi" }),
    ).getAllByRole('listitem');

const times = () =>
    items().map((item) => within(item).getByRole('time').textContent);

beforeEach(() => {
    resetIndexedDb();
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.spyOn(crypto, 'randomUUID')
        .mockReturnValueOnce(K1)
        .mockReturnValueOnce(K2);
    vi.spyOn(navigator, 'onLine', 'get').mockReturnValue(true);
    let objectUrls = 0;
    URL.createObjectURL = vi.fn(() => `blob:photo-${(objectUrls += 1)}`);
    URL.revokeObjectURL = vi.fn();
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    window.history.replaceState({}, '', '/import');
});

afterEach(() => {
    act(() => {
        toast.dismiss();
    });
    vi.useRealTimers();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('section « EN ATTENTE D’ENVOI »', () => {
    it('is absent when no photo waits', async () => {
        await load();
        await show();

        await act(async () => {
            await new Promise((resolve) => setTimeout(resolve, 20));
        });
        expect(section()).toBeNull();
        expect(screen.queryByRole('alert')).toBeNull();
    });

    it('lists the photos of 21:04 and 21:06, oldest first, with their count, thumbnail and « Retirer »', async () => {
        const { queue } = await load();
        await enqueueAt(queue, T2, 'second');
        await enqueueAt(queue, T1, 'first');

        await show();

        await waitFor(() => expect(section()).not.toBeNull());
        expect(section()?.parentElement?.textContent).toBe(
            "En attente d'envoi2",
        );
        expect(times()).toEqual(['21:04', '21:06']);
        expect(
            items().map((item) =>
                within(item).getByRole('time').getAttribute('dateTime'),
            ),
        ).toEqual([T1, T2]);
        expect(
            items().map((item) =>
                item.querySelector('img')?.getAttribute('alt'),
            ),
        ).toEqual(['', '']);
        expect(
            items().map((item) =>
                item.querySelector('img')?.getAttribute('src'),
            ),
        ).toEqual(['blob:photo-1', 'blob:photo-2']);
        expect(
            items().map(
                (item) =>
                    (
                        within(item).getByRole('button', {
                            name: 'Retirer',
                        }) as HTMLButtonElement
                    ).disabled,
            ),
        ).toEqual([false, false]);
    });

    it('« Retirer » forgets the photo without sending anything', async () => {
        const { queue } = await load();
        await enqueueAt(queue, T1, 'first');
        await enqueueAt(queue, T2, 'second');
        await show();
        await waitFor(() => expect(times()).toHaveLength(2));

        await userEvent.click(
            within(items()[0]).getByRole('button', { name: 'Retirer' }),
        );

        await waitFor(() => expect(times()).toEqual(['21:06']));
        expect((await queue.list()).map((photo) => photo.key)).toEqual([K2]);
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('shows « Envoi de 2 photos… » and disables « Retirer » on the oldest while sending, then « 2 photos envoyées. »', async () => {
        const { queue, sync } = await load();
        await enqueueAt(queue, T1, 'first');
        await enqueueAt(queue, T2, 'second');
        await show();
        await waitFor(() => expect(times()).toHaveLength(2));
        const first = deferred<Response>();
        fetchMock
            .mockImplementationOnce(() => first.promise)
            .mockResolvedValueOnce(json(201, { id: 'b' }));

        let sending: Promise<unknown> = Promise.resolve();
        act(() => {
            sending = sync.sendPendingPhotos();
        });

        await waitFor(() =>
            expect(
                within(section()!.closest('section')!).getByRole('status')
                    .textContent,
            ).toBe('Envoi de 2 photos…'),
        );
        expect(
            items().map(
                (item) =>
                    (
                        within(item).getByRole('button', {
                            name: 'Retirer',
                        }) as HTMLButtonElement
                    ).disabled,
            ),
        ).toEqual([true, false]);

        await act(async () => {
            first.resolve(json(201, { id: 'a' }));
            await sending;
        });

        await waitFor(() => expect(section()).toBeNull());
        expect(
            await screen.findByText('2 photos envoyées.'),
        ).toBeInTheDocument();
    });

    it('says « Envoi d’1 photo… » for a single photo', async () => {
        const { queue, sync } = await load();
        await enqueueAt(queue, T1, 'first');
        await show();
        await waitFor(() => expect(times()).toHaveLength(1));
        const response = deferred<Response>();
        fetchMock.mockImplementationOnce(() => response.promise);

        let sending: Promise<unknown> = Promise.resolve();
        act(() => {
            sending = sync.sendPendingPhotos();
        });

        await waitFor(() =>
            expect(
                within(section()!.closest('section')!).getByRole('status')
                    .textContent,
            ).toBe('Envoi d’1 photo…'),
        );

        await act(async () => {
            response.resolve(json(201, { id: 'a' }));
            await sending;
        });
    });

    it('shows the refusal of the server with « Saisie manuelle » after a 422, and no photo is left', async () => {
        const { queue, sync } = await load();
        await enqueueAt(queue, T1, 'first');
        await enqueueAt(queue, T2, 'second');
        await show();
        await waitFor(() => expect(times()).toHaveLength(2));
        fetchMock
            .mockResolvedValueOnce(json(422, { error: DISABLED }))
            .mockResolvedValueOnce(json(422, { error: DISABLED }));

        await act(async () => {
            await sync.sendPendingPhotos();
        });

        const refusal = await screen.findByRole('alert');
        expect(within(refusal).getByText(DISABLED)).toBeVisible();
        expect(
            within(refusal)
                .getByRole('link', { name: 'Saisie manuelle' })
                .getAttribute('href'),
        ).toBe('/rounds/new');
        await waitFor(() => expect(section()).toBeNull());
    });

    it('leaves out the photo the upload tiles are sending themselves', async () => {
        const { sync } = await load();
        await show();
        const response = deferred<Response>();
        fetchMock.mockImplementationOnce(() => response.promise);

        let upload: Promise<unknown> = Promise.resolve();
        act(() => {
            upload = sync.uploadPhoto(
                new Blob(['jpeg'], { type: 'image/jpeg' }),
                () => {},
            );
        });
        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1));

        expect(section()).toBeNull();

        await act(async () => {
            response.resolve(json(201, { id: 'a' }));
            await upload;
        });
        expect(section()).toBeNull();
    });
});
