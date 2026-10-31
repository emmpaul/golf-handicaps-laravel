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

// Spec offline-mode « Page jamais visitée hors ligne », « Écritures hors ligne
// refusées », « Bandeau hors ligne »; design D4, D5. The real Inertia router
// listens: each test fires the DOM event the router fires (`inertia:start`,
// `inertia:httpException`, `inertia:networkError`), the browser events
// `online` / `offline`, and the message of the service worker. Each test loads
// a fresh module, as a page load would.
// Mutations:
// - the write message shown for every visit (method not looked at) → the GET
//   network error test goes red;
// - `X-Offline: miss` not looked at (every 503 taken for a missing copy) → the
//   « other 503 » test goes red;
// - the httpException not cancelled (no `return false`) → the error dialog of
//   Inertia would open: the « stays on the page » test goes red;
// - the copy kept when a new visit starts → the « new visit » test goes red;
// - `online` not listened → the « back online » test goes red.

type Offline = typeof import('@/lib/offline');

let uninstall: () => void = () => {};
let worker: EventTarget;
let online = true;

async function install(): Promise<Offline> {
    vi.resetModules();
    const offline = await import('@/lib/offline');
    uninstall = offline.installOfflineHandling();

    return offline;
}

/** Fires an Inertia global event; returns false when a listener cancelled it. */
function fire(name: string, detail: unknown): boolean {
    let notCancelled = true;

    act(() => {
        notCancelled = document.dispatchEvent(
            new CustomEvent(`inertia:${name}`, { cancelable: true, detail }),
        );
    });

    return notCancelled;
}

function startVisit(method: string) {
    fire('start', { visit: { method, url: new URL('http://localhost/x') } });
}

function finishVisit(method: string) {
    fire('finish', { visit: { method, url: new URL('http://localhost/x') } });
}

function browser(event: 'online' | 'offline') {
    online = event === 'online';
    act(() => {
        window.dispatchEvent(new Event(event));
    });
}

function servedFromCache(url: string, storedAt: string) {
    act(() => {
        worker.dispatchEvent(
            new MessageEvent('message', {
                data: { type: 'served-from-cache', url, storedAt },
            }),
        );
    });
}

const toasts = () =>
    screen.queryAllByRole('alert').map((toast) => toast.textContent);

beforeEach(() => {
    online = true;
    vi.spyOn(navigator, 'onLine', 'get').mockImplementation(() => online);
    worker = new EventTarget();
    Object.defineProperty(navigator, 'serviceWorker', {
        configurable: true,
        value: worker,
    });
    render(<Toaster />);
});

afterEach(() => {
    uninstall();
    act(() => {
        toast.dismiss();
    });
    vi.restoreAllMocks();
    Reflect.deleteProperty(navigator, 'serviceWorker');
});

describe('page without copy', () => {
    it('stays on the page and shows « Page indisponible hors ligne. » for a 503 X-Offline: miss', async () => {
        await install();
        startVisit('get');

        const notCancelled = fire('httpException', {
            response: {
                status: 503,
                data: '',
                headers: { 'x-offline': 'miss' },
            },
        });

        expect(notCancelled).toBe(false);
        expect(await screen.findByRole('alert')).toHaveTextContent(
            'Page indisponible hors ligne.',
        );
    });

    it('leaves any other 503 to Inertia', async () => {
        await install();
        startVisit('get');

        const notCancelled = fire('httpException', {
            response: { status: 503, data: 'Service Unavailable', headers: {} },
        });

        expect(notCancelled).toBe(true);
        expect(toasts()).toEqual([]);
    });
});

describe('write without network', () => {
    it('shows « Hors ligne : rien n’a été envoyé. » when a POST ends in a network error', async () => {
        await install();
        startVisit('post');

        fire('networkError', { error: new Error('Network Error') });

        expect(await screen.findByRole('alert')).toHaveTextContent(
            "Hors ligne : rien n'a été envoyé.",
        );
    });

    it('does not show it when a GET ends in a network error', async () => {
        await install();
        startVisit('post');
        finishVisit('post');
        startVisit('get');

        fire('networkError', { error: new Error('Network Error') });

        await act(async () => {
            await new Promise((resolve) => setTimeout(resolve, 20));
        });
        expect(toasts()).toEqual([]);
    });
});

describe('offline state', () => {
    it('is online, without copy, while the device has network', async () => {
        const offline = await install();

        expect(offline.offlineSnapshot()).toEqual({ online: true, copy: null });
    });

    it('follows the offline and online events', async () => {
        const offline = await install();
        const listener = vi.fn();
        offline.subscribeOffline(listener);

        browser('offline');
        expect(offline.offlineSnapshot()).toEqual({
            online: false,
            copy: null,
        });

        browser('online');
        expect(offline.offlineSnapshot()).toEqual({ online: true, copy: null });
        expect(listener).toHaveBeenCalledTimes(2);
    });

    it('keeps the copy the service worker served, with its instant', async () => {
        const offline = await install();

        servedFromCache(
            'http://localhost/rounds?page=2',
            '2026-10-31T01:04:00.000Z',
        );

        expect(offline.offlineSnapshot()).toEqual({
            online: true,
            copy: {
                url: 'http://localhost/rounds?page=2',
                storedAt: '2026-10-31T01:04:00.000Z',
            },
        });
    });

    it('ignores the other messages of the service worker', async () => {
        const offline = await install();

        act(() => {
            worker.dispatchEvent(
                new MessageEvent('message', { data: { type: 'other' } }),
            );
        });

        expect(offline.offlineSnapshot().copy).toBeNull();
    });

    it('forgets the copy when a new visit starts', async () => {
        const offline = await install();
        servedFromCache('http://localhost/rounds', '2026-10-31T01:04:00.000Z');

        startVisit('get');

        expect(offline.offlineSnapshot().copy).toBeNull();
    });

    it('forgets the copy when the network comes back', async () => {
        const offline = await install();
        browser('offline');
        servedFromCache('http://localhost/rounds', '2026-10-31T01:04:00.000Z');

        browser('online');

        expect(offline.offlineSnapshot()).toEqual({ online: true, copy: null });
    });

    it('stops listening once uninstalled', async () => {
        const offline = await install();
        uninstall();

        browser('offline');
        servedFromCache('http://localhost/rounds', '2026-10-31T01:04:00.000Z');

        expect(offline.offlineSnapshot()).toEqual({ online: true, copy: null });
    });
});
