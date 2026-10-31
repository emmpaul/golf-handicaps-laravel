import { act, render, screen } from '@testing-library/react';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';

import { OfflineBanner } from '@/components/offline-banner';
import { installOfflineHandling } from '@/lib/offline';

// Spec offline-mode « Bandeau hors ligne » (scenarios « Copie affichée »,
// « Retour du réseau », « En ligne »); design D5. The real offline state of
// `lib/offline.ts` runs; the browser events and the message of the service
// worker are fired by hand. usePage gives the current page. Tests run under
// TZ=America/Montreal.
// Mutations:
// - the hour of the copy in UTC → « page du 31 oct. à 01:04 », the copy test
//   goes red;
// - the copy shown whatever its page (url not compared) → the « other page »
//   test goes red;
// - the banner shown while online without copy → the « online » test goes red;
// - the status region mounted only once offline → the role test goes red.

const page = vi.hoisted(() => ({ url: '/rounds' }));

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    usePage: () => ({ url: page.url, props: {} }),
}));

let uninstall: () => void = () => {};
let worker: EventTarget;
let online = true;

function browser(event: 'online' | 'offline') {
    online = event === 'online';
    act(() => {
        window.dispatchEvent(new Event(event));
    });
}

function servedFromCache(url: string) {
    act(() => {
        worker.dispatchEvent(
            new MessageEvent('message', {
                data: {
                    type: 'served-from-cache',
                    url,
                    storedAt: '2026-10-31T01:04:00.000Z',
                },
            }),
        );
    });
}

const banner = () =>
    document.querySelector<HTMLElement>('[data-slot="offline-banner"]')!;

beforeEach(() => {
    page.url = '/rounds';
    online = true;
    vi.spyOn(navigator, 'onLine', 'get').mockImplementation(() => online);
    worker = new EventTarget();
    Object.defineProperty(navigator, 'serviceWorker', {
        configurable: true,
        value: worker,
    });
    uninstall = installOfflineHandling();
});

afterEach(() => {
    uninstall();
    vi.restoreAllMocks();
    Reflect.deleteProperty(navigator, 'serviceWorker');
});

describe('OfflineBanner', () => {
    it('shows nothing online, in an empty status region mounted beforehand', () => {
        render(<OfflineBanner />);

        expect(screen.getByRole('status')).toBe(banner());
        expect(banner().textContent).toBe('');
        expect(banner().className).toContain('sr-only');
    });

    it('shows « Hors ligne » on the offline event', () => {
        render(<OfflineBanner />);

        browser('offline');

        expect(screen.getByRole('status').textContent).toBe('Hors ligne');
        expect(banner().className).not.toContain('sr-only');
    });

    it('shows « Hors ligne · page du 30 oct. à 21:04 » for the copy of the current page', () => {
        render(<OfflineBanner />);

        browser('offline');
        servedFromCache('http://localhost/rounds');

        expect(screen.getByRole('status').textContent).toBe(
            'Hors ligne · page du 30 oct. à 21:04',
        );
    });

    it('shows the copy even when the device believes it is online', () => {
        page.url = '/rounds?player=2';
        render(<OfflineBanner />);

        servedFromCache('http://localhost/rounds?player=2');

        expect(screen.getByRole('status').textContent).toBe(
            'Hors ligne · page du 30 oct. à 21:04',
        );
    });

    it('ignores the copy of another page', () => {
        render(<OfflineBanner />);

        servedFromCache('http://localhost/handicaps');

        expect(banner().textContent).toBe('');
    });

    it('disappears when the network comes back', () => {
        render(<OfflineBanner />);
        browser('offline');
        servedFromCache('http://localhost/rounds');

        browser('online');

        expect(banner().textContent).toBe('');
        expect(banner().className).toContain('sr-only');
    });
});
