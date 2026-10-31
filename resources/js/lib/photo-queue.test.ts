import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';

import { resetIndexedDb } from '@/tests/indexeddb';

// Spec scorecard-import « Photo gardée hors ligne », « Envoi au retour du
// réseau », « Envoi interrompu », « Envoi sans doublon », « Reprise de l'envoi
// après la saisie du code », « Photo refusée à l'envoi », and design D7: the
// photos wait in IndexedDB (`golf-offline`, store `photos`) and leave one at a
// time, oldest first; a photo leaves the device only once the server created
// or refused it. `fetch` is the only collaborator replaced; IndexedDB is
// fake-indexeddb, BroadcastChannel is Node's. Each test loads a fresh module,
// as a page load would. Mutations:
// - list read in key order instead of `takenAt` order → the order test and the
//   flush order test go red (the keys below sort the other way round);
// - photo deleted before the response → the 401, 500, network error and
//   timeout tests go red;
// - 422 keeps the photo, or the `error` field ignored → the refusal test goes
//   red;
// - `Accept: application/json` dropped → the request test goes red;
// - no GET before the 419 retry, or the token read once per flush → the 419
//   test goes red; retry without limit → the second 419 test goes red;
// - timeout constant other than 60 s → the timeout test goes red;
// - second flush of the tab not chained → the « two flushes » test goes red;
//   lock not requested → the « two tabs » test goes red;
// - persistence asked on every enqueue → the persistence test goes red;
// - channel message dropped, or sent without the key being sent → the
//   « other tab » test goes red.

type Queue = typeof import('@/lib/photo-queue');

// Keys are drawn in this order and sort the other way round (K3 < K2 < K1):
// a list read in key order would not follow the times of the photos.
const K1 = '7f3c1a52-0d4e-4b8a-9c61-2e5f8a9b0c11';
const K2 = '1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d';
const K3 = '0b9e8d7c-6b5a-4f3e-9d2c-1b0a9f8e7d6c';
// 21:04, 21:06 and 21:09 in Montréal.
const T1 = '2026-10-31T01:04:00.000Z';
const T2 = '2026-10-31T01:06:00.000Z';
const T3 = '2026-10-31T01:09:00.000Z';

const DISABLED =
    'Lecture des photos désactivée : aucune clé d’IA n’est configurée.';

const fetchMock = vi.fn<typeof fetch>();

async function loadQueue(): Promise<Queue> {
    vi.resetModules();

    return import('@/lib/photo-queue');
}

async function enqueueAt(queue: Queue, at: string, content: string) {
    vi.setSystemTime(new Date(at));

    return queue.enqueue(new Blob([content], { type: 'image/jpeg' }));
}

/** Two photos: K1 at 21:04 (« first »), then K2 at 21:06 (« second »). */
async function twoPhotos(queue: Queue) {
    await enqueueAt(queue, T1, 'first');
    await enqueueAt(queue, T2, 'second');
}

async function keys(queue: Queue) {
    return (await queue.list()).map((photo) => photo.key);
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

/** Next `fetch` call stays open until `respond` is called; `called` settles when it starts. */
function holdNextFetch() {
    const called = deferred<RequestInit | undefined>();
    const response = deferred<Response>();

    fetchMock.mockImplementationOnce((_input, init) => {
        called.resolve(init);

        return response.promise;
    });

    return { called: called.promise, respond: response.resolve };
}

function call(index: number) {
    const [url, init] = fetchMock.mock.calls[index];

    return { url, init: init as RequestInit };
}

async function sentPhoto(index: number) {
    const body = call(index).init.body as FormData;
    const photo = body.get('photo') as File;

    return {
        uploadKey: body.get('upload_key'),
        name: photo.name,
        content: await photo.text(),
    };
}

/** Same-origin lock manager: one holder per name, the others wait in turn. */
function fakeLocks() {
    const tails = new Map<string, Promise<unknown>>();

    return {
        request: vi.fn(
            (name: string, callback: (lock: unknown) => Promise<unknown>) => {
                const previous = tails.get(name) ?? Promise.resolve();
                const run = previous.then(() =>
                    callback({ name, mode: 'exclusive' }),
                );

                tails.set(
                    name,
                    run.catch(() => undefined),
                );

                return run;
            },
        ),
    };
}

const NOTHING_SENT = {
    sent: 0,
    sentKeys: [],
    rejected: [],
    login: false,
    stopped: false,
};

beforeEach(() => {
    resetIndexedDb();
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.spyOn(crypto, 'randomUUID')
        .mockReturnValueOnce(K1)
        .mockReturnValueOnce(K2)
        .mockReturnValueOnce(K3);
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    document.cookie = 'XSRF-TOKEN=tok%3Den; path=/';
});

afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    document.cookie =
        'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
    Reflect.deleteProperty(navigator, 'locks');
    Reflect.deleteProperty(navigator, 'storage');
});

describe('photo queue on the device', () => {
    it('lists the photos from the oldest to the newest with their key and time', async () => {
        const queue = await loadQueue();

        expect(await enqueueAt(queue, T2, 'middle')).toEqual({
            key: K1,
            takenAt: T2,
        });
        await enqueueAt(queue, T3, 'newest');
        await enqueueAt(queue, T1, 'oldest');

        const photos = await queue.list();

        expect(
            await Promise.all(
                photos.map(async (photo) => ({
                    key: photo.key,
                    takenAt: photo.takenAt,
                    content: await photo.blob.text(),
                })),
            ),
        ).toEqual([
            { key: K3, takenAt: T1, content: 'oldest' },
            { key: K1, takenAt: T2, content: 'middle' },
            { key: K2, takenAt: T3, content: 'newest' },
        ]);
    });

    it('keeps the photos for the next page load', async () => {
        await twoPhotos(await loadQueue());

        expect(await keys(await loadQueue())).toEqual([K1, K2]);
    });

    it('remove forgets one photo and keeps the others', async () => {
        const queue = await loadQueue();
        await twoPhotos(queue);

        await queue.remove(K1);

        expect(await keys(queue)).toEqual([K2]);
    });

    it('asks the browser once to keep its storage', async () => {
        const persist = vi.fn(() => Promise.resolve(true));
        Object.defineProperty(navigator, 'storage', {
            value: { persist },
            configurable: true,
        });
        const queue = await loadQueue();

        await twoPhotos(queue);

        expect(persist).toHaveBeenCalledTimes(1);
    });
});

describe('flush', () => {
    it('sends the photos one at a time, oldest first, as JSON form posts', async () => {
        const queue = await loadQueue();
        await twoPhotos(queue);
        const first = holdNextFetch();
        fetchMock.mockResolvedValueOnce(json(200, { id: 8 }));

        const outcome = queue.flush();
        await first.called;

        expect(fetchMock).toHaveBeenCalledTimes(1);

        first.respond(json(201, { id: 7 }));

        expect(await outcome).toEqual({
            sent: 2,
            sentKeys: [K1, K2],
            rejected: [],
            login: false,
            stopped: false,
        });
        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(call(0).url).toBe('/imports');
        expect(call(0).init.method).toBe('POST');
        expect(call(0).init.headers).toEqual({
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': 'tok=en',
        });
        expect(await sentPhoto(0)).toEqual({
            uploadKey: K1,
            name: 'carte.jpg',
            content: 'first',
        });
        expect(call(1).url).toBe('/imports');
        expect(await sentPhoto(1)).toEqual({
            uploadKey: K2,
            name: 'carte.jpg',
            content: 'second',
        });
        expect(await queue.list()).toEqual([]);
    });

    it('removes a photo refused with 422, returns the refusal and sends the next ones', async () => {
        const queue = await loadQueue();
        await twoPhotos(queue);
        await enqueueAt(queue, T3, 'third');
        fetchMock
            .mockResolvedValueOnce(json(422, { error: DISABLED }))
            .mockResolvedValueOnce(
                json(422, {
                    message: 'La photo dépasse 20 Mo (and 1 more error)',
                    errors: {
                        photo: ['La photo dépasse 20 Mo'],
                        upload_key: ['Clé d’envoi invalide'],
                    },
                }),
            )
            .mockResolvedValueOnce(json(201, { id: 9 }));

        expect(await queue.flush()).toEqual({
            sent: 1,
            sentKeys: [K3],
            rejected: [
                { key: K1, takenAt: T1, message: DISABLED },
                { key: K2, takenAt: T2, message: 'La photo dépasse 20 Mo' },
            ],
            login: false,
            stopped: false,
        });
        expect(await queue.list()).toEqual([]);
    });

    // A photo over the body limit of the proxy gets 413 before Laravel reads
    // it: it would fail again on every send, so it is refused like a 422.
    // Mutation: 413 left to the default branch (stop, keep) → red.
    it('removes a photo answered 413 as « La photo dépasse 20 Mo » and sends the next one', async () => {
        const queue = await loadQueue();
        await twoPhotos(queue);
        fetchMock
            .mockResolvedValueOnce(
                new Response('<html>413 Request Entity Too Large</html>', {
                    status: 413,
                }),
            )
            .mockResolvedValueOnce(json(201, { id: 9 }));

        expect(await queue.flush()).toEqual({
            sent: 1,
            sentKeys: [K2],
            rejected: [
                { key: K1, takenAt: T1, message: 'La photo dépasse 20 Mo' },
            ],
            login: false,
            stopped: false,
        });
        expect(await queue.list()).toEqual([]);
    });

    it('stops on 401, keeps every photo and asks for the login', async () => {
        const queue = await loadQueue();
        await twoPhotos(queue);
        fetchMock.mockResolvedValueOnce(
            json(401, { error: 'Session requise' }),
        );

        expect(await queue.flush()).toEqual({
            ...NOTHING_SENT,
            login: true,
            stopped: true,
        });
        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(await keys(queue)).toEqual([K1, K2]);
    });

    it.each([
        [
            'a 500',
            () => Promise.resolve(json(500, { message: 'Server Error' })),
        ],
        [
            'a network error',
            () => Promise.reject(new TypeError('Failed to fetch')),
        ],
    ])(
        'stops on %s and keeps every photo',
        async (_case, respond: () => Promise<Response>) => {
            const queue = await loadQueue();
            await twoPhotos(queue);
            fetchMock.mockImplementationOnce(respond);

            expect(await queue.flush()).toEqual({
                ...NOTHING_SENT,
                stopped: true,
            });
            expect(fetchMock).toHaveBeenCalledTimes(1);
            expect(await keys(queue)).toEqual([K1, K2]);
        },
    );

    it('gives up a send after 60 s and keeps every photo', async () => {
        vi.useFakeTimers({ toFake: ['Date', 'setTimeout', 'clearTimeout'] });
        const queue = await loadQueue();
        await twoPhotos(queue);
        const called = deferred<AbortSignal>();
        fetchMock.mockImplementationOnce((_input, init) => {
            const signal = init?.signal as AbortSignal;
            called.resolve(signal);

            return new Promise((_resolve, reject) => {
                signal.addEventListener('abort', () =>
                    reject(new DOMException('aborted', 'AbortError')),
                );
            });
        });

        const outcome = queue.flush();
        const signal = await called.promise;

        await vi.advanceTimersByTimeAsync(59_999);
        expect(signal.aborted).toBe(false);

        await vi.advanceTimersByTimeAsync(1);
        expect(signal.aborted).toBe(true);
        expect(await outcome).toEqual({ ...NOTHING_SENT, stopped: true });
        expect(await keys(queue)).toEqual([K1, K2]);
    });

    it('retries once after a 419, with the token renewed by a GET of the page', async () => {
        const queue = await loadQueue();
        await enqueueAt(queue, T1, 'first');
        fetchMock
            .mockResolvedValueOnce(json(419, { message: 'Page Expired' }))
            .mockImplementationOnce(() => {
                document.cookie = 'XSRF-TOKEN=fresh%3Dtoken; path=/';

                return Promise.resolve(new Response('<html></html>'));
            })
            .mockResolvedValueOnce(json(201, { id: 7 }));

        expect(await queue.flush()).toEqual({
            sent: 1,
            sentKeys: [K1],
            rejected: [],
            login: false,
            stopped: false,
        });
        expect(fetchMock).toHaveBeenCalledTimes(3);
        expect(call(1).url).toBe('http://localhost:3000/');
        expect(call(1).init.method).toBe('GET');
        expect(call(2).url).toBe('/imports');
        expect(call(2).init.headers).toEqual({
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': 'fresh=token',
        });
        expect(await sentPhoto(2)).toEqual({
            uploadKey: K1,
            name: 'carte.jpg',
            content: 'first',
        });
    });

    it('stops and keeps the photo when the retry after a 419 gets a 419 again', async () => {
        const queue = await loadQueue();
        await enqueueAt(queue, T1, 'first');
        // Two 419, then a 201 that only a second retry would reach.
        fetchMock
            .mockResolvedValueOnce(json(419, { message: 'Page Expired' }))
            .mockResolvedValueOnce(new Response('<html></html>'))
            .mockResolvedValueOnce(json(419, { message: 'Page Expired' }))
            .mockResolvedValueOnce(new Response('<html></html>'))
            .mockResolvedValueOnce(json(201, { id: 7 }));

        expect(await queue.flush()).toEqual({ ...NOTHING_SENT, stopped: true });
        expect(fetchMock).toHaveBeenCalledTimes(3);
        expect(await keys(queue)).toEqual([K1]);
    });

    it('two flushes at the same time send each photo once', async () => {
        const queue = await loadQueue();
        await twoPhotos(queue);
        fetchMock.mockImplementation(() =>
            Promise.resolve(json(201, { id: 7 })),
        );

        const outcomes = await Promise.all([queue.flush(), queue.flush()]);

        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(outcomes).toEqual([
            {
                sent: 2,
                sentKeys: [K1, K2],
                rejected: [],
                login: false,
                stopped: false,
            },
            NOTHING_SENT,
        ]);
    });

    it('two tabs flushing at the same time send each photo once', async () => {
        const locks = fakeLocks();
        Object.defineProperty(navigator, 'locks', {
            value: locks,
            configurable: true,
        });
        const tab = await loadQueue();
        const otherTab = await loadQueue();
        await twoPhotos(tab);
        fetchMock.mockImplementation(() =>
            Promise.resolve(json(201, { id: 7 })),
        );

        await Promise.all([tab.flush(), otherTab.flush()]);

        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(await sentPhoto(0)).toMatchObject({ uploadKey: K1 });
        expect(await sentPhoto(1)).toMatchObject({ uploadKey: K2 });
        expect(locks.request.mock.calls.map(([name]) => name)).toEqual([
            'golf-offline-photos',
            'golf-offline-photos',
        ]);
    });
});

describe('changes', () => {
    it('tells the listeners which photo is being sent', async () => {
        const queue = await loadQueue();
        await twoPhotos(queue);
        const seen: (string | null)[] = [];
        const stop = queue.subscribe(() => seen.push(queue.sendingKey()));
        const first = holdNextFetch();
        fetchMock.mockResolvedValueOnce(json(201, { id: 8 }));

        const outcome = queue.flush();
        await first.called;

        expect(queue.sendingKey()).toBe(K1);

        first.respond(json(201, { id: 7 }));
        await outcome;
        stop();

        expect(seen).toEqual([K1, null, K2, null]);
        expect(queue.sendingKey()).toBeNull();
    });

    it('tells the listeners of another tab', async () => {
        const tab = await loadQueue();
        const otherTab = await loadQueue();
        const seen: (string | null)[] = [];
        const stop = otherTab.subscribe(() => seen.push(otherTab.sendingKey()));
        fetchMock.mockResolvedValueOnce(json(201, { id: 7 }));

        await enqueueAt(tab, T1, 'first');
        await vi.waitFor(() => expect(seen).toEqual([null]));

        await tab.flush();
        await vi.waitFor(() => expect(seen).toEqual([null, K1, null]));
        stop();
    });

    // Mutation: `sending` reset after the delete instead of in a `finally` →
    // the key stays « being sent » after the failed delete (« Retirer »
    // disabled for good): the test goes red.
    it('forgets the photo being sent when the device fails to delete it after the send', async () => {
        const queue = await loadQueue();
        await enqueueAt(queue, T1, 'first');
        const seen: (string | null)[] = [];
        const stop = queue.subscribe(() => seen.push(queue.sendingKey()));
        fetchMock.mockResolvedValueOnce(json(201, { id: 7 }));
        vi.spyOn(IDBObjectStore.prototype, 'delete').mockImplementation(() => {
            throw new DOMException('Disk failure', 'UnknownError');
        });

        await expect(queue.flush()).rejects.toThrow('Disk failure');
        stop();

        expect(queue.sendingKey()).toBeNull();
        expect(seen).toEqual([K1, null]);
    });

    it('stops telling a listener once it unsubscribes', async () => {
        const queue = await loadQueue();
        const listener = vi.fn();
        const stop = queue.subscribe(listener);

        await enqueueAt(queue, T1, 'first');
        stop();
        await enqueueAt(queue, T2, 'second');

        expect(listener).toHaveBeenCalledTimes(1);
    });
});
