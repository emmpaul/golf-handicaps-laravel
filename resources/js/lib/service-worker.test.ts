import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';

// Spec offline-mode « Écran Importer disponible hors ligne », design D1, D2:
// the application registers `public/sw.js`, a classic script at the root,
// only with the compiled assets (`import.meta.env.PROD`, stubbed per test);
// on the first page shown online with a session, it asks the service worker
// to keep /import in both forms (`cache-import` with the Inertia version) and
// loads the chunk of the import page. The Inertia router and the service
// worker container are the collaborators replaced.
// Mutations:
// - registration without the `PROD` guard → the development test goes red;
// - `{ type: 'module' }` passed to register → the registration test goes red;
// - the message posted on every page → the « once » test goes red;
// - the message posted on /login (no session) → the login test goes red;
// - the version left out of the message → the message test goes red;
// - the chunk of the import page not loaded → the chunk test goes red.

const inertia = vi.hoisted(() => ({
    navigate: undefined as ((event: unknown) => unknown) | undefined,
}));

const chunk = vi.hoisted(() => ({ loaded: 0 }));

vi.mock('@inertiajs/react', () => ({
    router: {
        on: (type: string, handler: (event: unknown) => unknown) => {
            if (type === 'navigate') {
                inertia.navigate = handler;
            }

            return () => {
                inertia.navigate = undefined;
            };
        },
    },
}));

vi.mock('@/pages/import/index', () => {
    chunk.loaded += 1;

    return { default: () => null };
});

const postMessage = vi.fn();
const register = vi.fn();
let online = true;

async function start() {
    vi.resetModules();
    const { registerServiceWorker } = await import('@/lib/service-worker');

    registerServiceWorker();
}

async function show(component: string, version = 'b7e1c0') {
    inertia.navigate?.({
        detail: { page: { component, version, url: '/' }, type: 'initial' },
    });

    for (let turn = 0; turn < 10; turn += 1) {
        await new Promise((resolve) => setTimeout(resolve, 0));
    }
}

beforeEach(() => {
    inertia.navigate = undefined;
    chunk.loaded = 0;
    online = true;
    postMessage.mockReset();
    register.mockReset().mockResolvedValue({});
    vi.spyOn(navigator, 'onLine', 'get').mockImplementation(() => online);
    Object.defineProperty(navigator, 'serviceWorker', {
        configurable: true,
        value: {
            register,
            controller: null,
            ready: Promise.resolve({ active: { postMessage } }),
        },
    });
});

afterEach(() => {
    vi.unstubAllEnvs();
    vi.restoreAllMocks();
    Reflect.deleteProperty(navigator, 'serviceWorker');
});

describe('service worker registration', () => {
    it('registers nothing in development, under the Vite server', async () => {
        vi.stubEnv('PROD', false);

        await start();
        await show('home');

        expect(register).not.toHaveBeenCalled();
        expect(postMessage).not.toHaveBeenCalled();
    });

    it('registers /sw.js as a classic script with the compiled assets', async () => {
        vi.stubEnv('PROD', true);

        await start();

        expect(register.mock.calls).toEqual([['/sw.js']]);
    });

    it('asks once to keep /import, with the Inertia version, on the first page with a session', async () => {
        vi.stubEnv('PROD', true);

        await start();
        await show('home', 'b7e1c0');
        await show('rounds/index', 'b7e1c0');

        expect(postMessage.mock.calls).toEqual([
            [{ type: 'cache-import', version: 'b7e1c0' }],
        ]);
    });

    it('loads the chunk of the import page', async () => {
        vi.stubEnv('PROD', true);
        // A factory of vi.mock runs once per file: this one counts this test.
        vi.doMock('@/pages/import/index', () => {
            chunk.loaded += 1;

            return { default: () => null };
        });

        await start();
        expect(chunk.loaded).toBe(0);
        await show('home');

        await vi.waitFor(() => expect(chunk.loaded).toBe(1));
    });

    it('waits for the page after /login, where there is no session yet', async () => {
        vi.stubEnv('PROD', true);

        await start();
        await show('login');
        expect(postMessage).not.toHaveBeenCalled();

        await show('home', 'c0ffee');

        expect(postMessage.mock.calls).toEqual([
            [{ type: 'cache-import', version: 'c0ffee' }],
        ]);
    });

    it('waits for a page shown online', async () => {
        vi.stubEnv('PROD', true);
        online = false;

        await start();
        await show('home');
        expect(postMessage).not.toHaveBeenCalled();

        online = true;
        await show('handicaps/index');

        expect(postMessage).toHaveBeenCalledTimes(1);
    });
});
