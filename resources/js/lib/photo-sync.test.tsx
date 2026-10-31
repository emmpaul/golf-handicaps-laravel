import { act, render, screen } from '@testing-library/react';
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

// Spec scorecard-import « Envoi au retour du réseau », « Reprise de l'envoi
// après la saisie du code », « Photo refusée à l'envoi »; design D7: the
// application sends the photos waiting on the device when it opens, when the
// network comes back, when it comes back to the foreground and after a login;
// a 401 shows /login and keeps the photo. The queue is the real one over
// fake-indexeddb; `fetch` and the Inertia router are the collaborators
// replaced. Each test loads fresh modules, as a page load would.
// Mutations:
// - the `online` listener removed → the « network comes back » test goes red;
// - `visibilitychange` not listened → the foreground test goes red;
// - no send at opening → the opening test goes red;
// - `router.visit('/login')` not called on a 401 → the 401 test goes red;
// - the send made on /login too → the « on /login » test goes red;
// - « N photos envoyées. » always singular → the two-photo test goes red;
// - the list of imports not reloaded on /import → the two-photo test goes red;
// - refusals not kept → the 422 test goes red.

const inertia = vi.hoisted(() => ({
    visit: vi.fn(),
    reload: vi.fn(),
    handlers: new Map<string, (event: unknown) => unknown>(),
}));

vi.mock('@inertiajs/react', () => ({
    router: {
        visit: inertia.visit,
        reload: inertia.reload,
        on: (type: string, handler: (event: unknown) => unknown) => {
            inertia.handlers.set(type, handler);

            return () => inertia.handlers.delete(type);
        },
    },
}));

type Sync = typeof import('@/lib/photo-sync');
type Queue = typeof import('@/lib/photo-queue');

const K1 = '7f3c1a52-0d4e-4b8a-9c61-2e5f8a9b0c11';
const K2 = '1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d';
const T1 = '2026-10-31T01:04:00.000Z';
const T2 = '2026-10-31T01:06:00.000Z';
const DISABLED =
    'Lecture des photos désactivée : aucune clé d’IA n’est configurée.';

const fetchMock = vi.fn<typeof fetch>();
let online = true;
let visibility: DocumentVisibilityState = 'visible';
let stop: () => void = () => {};

async function load(): Promise<{ sync: Sync; queue: Queue }> {
    vi.resetModules();

    return {
        sync: await import('@/lib/photo-sync'),
        queue: await import('@/lib/photo-queue'),
    };
}

async function enqueueAt(queue: Queue, at: string) {
    vi.setSystemTime(new Date(at));

    return queue.enqueue(new Blob([at], { type: 'image/jpeg' }));
}

function json(status: number, body: unknown): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

async function keys(queue: Queue) {
    return (await queue.list()).map((photo) => photo.key);
}

/** Lets the queue reach IndexedDB and fetch until nothing is left to do. */
async function settle() {
    await act(async () => {
        for (let turn = 0; turn < 20; turn += 1) {
            await new Promise((resolve) => setTimeout(resolve, 0));
        }
    });
}

const toasts = () =>
    screen.queryAllByRole('status').map((pill) => pill.textContent);

beforeEach(() => {
    resetIndexedDb();
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.spyOn(crypto, 'randomUUID')
        .mockReturnValueOnce(K1)
        .mockReturnValueOnce(K2);
    online = true;
    visibility = 'visible';
    vi.spyOn(navigator, 'onLine', 'get').mockImplementation(() => online);
    vi.spyOn(document, 'visibilityState', 'get').mockImplementation(
        () => visibility,
    );
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    inertia.visit.mockReset();
    inertia.reload.mockReset();
    inertia.handlers.clear();
    window.history.replaceState({}, '', '/');
    render(<Toaster />);
});

afterEach(() => {
    stop();
    stop = () => {};
    act(() => {
        toast.dismiss();
    });
    vi.useRealTimers();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('when the photos leave', () => {
    it('sends the waiting photos when the application opens with network', async () => {
        const { sync, queue } = await load();
        await enqueueAt(queue, T1);
        fetchMock.mockResolvedValueOnce(json(201, { id: 'a' }));

        stop = sync.startPhotoSync();
        await settle();

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock.mock.calls[0][0]).toBe('/imports');
        expect(await keys(queue)).toEqual([]);
    });

    it('sends nothing while the device is offline, then sends when the network comes back', async () => {
        const { sync, queue } = await load();
        await enqueueAt(queue, T1);
        online = false;

        stop = sync.startPhotoSync();
        await settle();
        expect(fetchMock).not.toHaveBeenCalled();

        fetchMock.mockResolvedValueOnce(json(201, { id: 'a' }));
        online = true;
        window.dispatchEvent(new Event('online'));
        await settle();

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(await keys(queue)).toEqual([]);
    });

    it('sends when the application comes back to the foreground', async () => {
        const { sync, queue } = await load();
        stop = sync.startPhotoSync();
        await settle();
        await enqueueAt(queue, T1);
        fetchMock.mockResolvedValueOnce(json(201, { id: 'a' }));

        visibility = 'hidden';
        document.dispatchEvent(new Event('visibilitychange'));
        await settle();
        expect(fetchMock).not.toHaveBeenCalled();

        visibility = 'visible';
        document.dispatchEvent(new Event('visibilitychange'));
        await settle();

        expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('sends nothing on /login, then sends once the page after the login shows', async () => {
        window.history.replaceState({}, '', '/login');
        const { sync, queue } = await load();
        await enqueueAt(queue, T1);

        stop = sync.startPhotoSync();
        await settle();
        expect(fetchMock).not.toHaveBeenCalled();

        fetchMock.mockResolvedValueOnce(json(201, { id: 'a' }));
        window.history.replaceState({}, '', '/');
        inertia.handlers.get('navigate')?.({
            detail: { page: { url: '/' } },
        });
        await settle();

        expect(fetchMock).toHaveBeenCalledTimes(1);
    });
});

describe('outcome of a send', () => {
    it('shows /login on a 401 and keeps the photo', async () => {
        const { sync, queue } = await load();
        await enqueueAt(queue, T1);
        fetchMock.mockResolvedValueOnce(
            json(401, { error: 'Session requise' }),
        );

        stop = sync.startPhotoSync();
        await settle();

        expect(inertia.visit).toHaveBeenCalledTimes(1);
        expect(inertia.visit.mock.calls[0][0]).toBe('/login');
        expect(await keys(queue)).toEqual([K1]);
    });

    it('says « 2 photos envoyées. » and reloads the imports on /import', async () => {
        window.history.replaceState({}, '', '/import');
        const { sync, queue } = await load();
        await enqueueAt(queue, T1);
        await enqueueAt(queue, T2);
        fetchMock
            .mockResolvedValueOnce(json(201, { id: 'a' }))
            .mockResolvedValueOnce(json(201, { id: 'b' }));

        await act(async () => {
            await sync.sendPendingPhotos();
        });

        expect(
            await screen.findByText('2 photos envoyées.'),
        ).toBeInTheDocument();
        expect(inertia.reload).toHaveBeenCalledTimes(1);
        expect(inertia.reload.mock.calls[0][0]).toEqual({
            only: ['imports', 'pendingImports'],
        });
    });

    it('says « 1 photo envoyée. » and does not reload elsewhere than /import', async () => {
        const { sync, queue } = await load();
        await enqueueAt(queue, T1);
        fetchMock.mockResolvedValueOnce(json(201, { id: 'a' }));

        await act(async () => {
            await sync.sendPendingPhotos();
        });

        expect(await screen.findByText('1 photo envoyée.')).toBeInTheDocument();
        expect(inertia.reload).not.toHaveBeenCalled();
    });

    it('keeps the refusal of a 422 to show it, and sends the next photo', async () => {
        const { sync, queue } = await load();
        await enqueueAt(queue, T1);
        await enqueueAt(queue, T2);
        fetchMock
            .mockResolvedValueOnce(json(422, { error: DISABLED }))
            .mockResolvedValueOnce(json(201, { id: 'b' }));

        await act(async () => {
            await sync.sendPendingPhotos();
        });

        expect(sync.syncSnapshot().refusals).toEqual([
            { key: K1, takenAt: T1, message: DISABLED },
        ]);
        expect(await keys(queue)).toEqual([]);
        expect(await screen.findByText('1 photo envoyée.')).toBeInTheDocument();
        expect(toasts()).toEqual(['1 photo envoyée.']);
    });
});

describe('a photo chosen on the import screen', () => {
    it('is sent at once online, with « Lecture en file d’attente. Tu peux envoyer la photo suivante. »', async () => {
        window.history.replaceState({}, '', '/import');
        const { sync, queue } = await load();
        fetchMock.mockResolvedValueOnce(json(201, { id: 'a' }));
        const onSending = vi.fn();

        let fate: string | undefined;
        await act(async () => {
            fate = await sync.uploadPhoto(
                new Blob(['jpeg'], { type: 'image/jpeg' }),
                onSending,
            );
        });

        expect(fate).toBe('sent');
        expect(onSending).toHaveBeenCalledTimes(1);
        expect(
            await screen.findByText(
                "Lecture en file d'attente. Tu peux envoyer la photo suivante.",
            ),
        ).toBeInTheDocument();
        expect(await keys(queue)).toEqual([]);
        expect(sync.syncSnapshot().uploading).toBeNull();
    });

    it('is kept offline without trying to send it', async () => {
        const { sync, queue } = await load();
        online = false;
        const onSending = vi.fn();

        let fate: string | undefined;
        await act(async () => {
            fate = await sync.uploadPhoto(
                new Blob(['jpeg'], { type: 'image/jpeg' }),
                onSending,
            );
        });

        expect(fate).toBe('kept');
        expect(onSending).not.toHaveBeenCalled();
        expect(fetchMock).not.toHaveBeenCalled();
        expect(await keys(queue)).toEqual([K1]);
    });

    it('is kept when the send fails on the way', async () => {
        const { sync, queue } = await load();
        fetchMock.mockRejectedValueOnce(new TypeError('Failed to fetch'));

        let fate: string | undefined;
        await act(async () => {
            fate = await sync.uploadPhoto(
                new Blob(['jpeg'], { type: 'image/jpeg' }),
                () => {},
            );
        });

        expect(fate).toBe('kept');
        expect(await keys(queue)).toEqual([K1]);
        await settle();
        expect(toasts()).toEqual([]);
    });

    it('is refused with the message of the server', async () => {
        const { sync } = await load();
        vi.setSystemTime(new Date(T1));
        fetchMock.mockResolvedValueOnce(
            json(422, { error: 'Photo illisible' }),
        );

        let fate: string | undefined;
        await act(async () => {
            fate = await sync.uploadPhoto(
                new Blob(['jpeg'], { type: 'image/jpeg' }),
                () => {},
            );
        });

        expect(fate).toBe('rejected');
        expect(sync.syncSnapshot().refusals).toEqual([
            { key: K1, takenAt: T1, message: 'Photo illisible' },
        ]);
    });
});
