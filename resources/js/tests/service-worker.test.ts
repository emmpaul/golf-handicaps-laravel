import { describe, expect, it, vi } from 'vite-plus/test';

import '../../../public/sw.js';

// Spec offline-mode (offline-pwa), « Pages visitées gardées sur l'appareil »,
// « Page jamais visitée hors ligne », « Pages gardées effacées à la fermeture
// de session »; design D2, D3 and D6. public/sw.js is a classic script with no
// build step: under Vitest `self.registration` is undefined, so it installs no
// listener and exposes its decision functions on
// `globalThis.offlineServiceWorker`. The wiring to the real Cache Storage and
// fetch events is checked by the browser pass (task 6.3).
// Mutations: `/login` stored (removed from the excluded paths) → the « login »
// case goes red; the `X-Inertia` suffix dropped from cacheKey → the Inertia
// key cases go red; the 401 body check dropped → « 401 for another reason »
// goes red; the assets bound written 3000 → the 301-entries case goes red; the
// 503 fallback answered for a document → the document fallback goes red.

type Cache = {
    keys(): Promise<readonly Request[]>;
    delete(request: Request): Promise<boolean>;
};

type ServiceWorkerLogic = {
    isAssetRequest(request: Request, origin: string): boolean;
    isPageRequest(request: Request, origin: string): boolean;
    shouldStore(request: Request, response: Response, origin: string): boolean;
    cacheKey(request: Request): string;
    clearsPages(
        request: Request,
        response: Response,
        origin: string,
    ): Promise<boolean>;
    offlineFallback(
        request: Request,
        offlinePage: Response | undefined,
    ): Response;
    storedCopy(response: Response, now: Date): Response;
    servedCopy(cached: Response): Response;
    servedFromCacheMessage(
        request: Request,
        cached: Response,
    ): { type: string; url: string; storedAt: string | null };
    importRequests(origin: string, version: string): Request[];
    trimCache(cache: Cache, cacheName: string): Promise<void>;
};

const sw = (
    globalThis as unknown as { offlineServiceWorker: ServiceWorkerLogic }
).offlineServiceWorker;

const ORIGIN = 'https://golf.example';
const ROUND = '0199a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a6b';

const get = (path: string, headers: Record<string, string> = {}) =>
    new Request(`${ORIGIN}${path}`, { headers });

const doc = (path: string) =>
    get(path, { Accept: 'text/html,application/xhtml+xml' });

const inertia = (path: string, headers: Record<string, string> = {}) =>
    get(path, { 'X-Inertia': 'true', ...headers });

const ok = () => new Response('<html></html>', { status: 200 });

/** A Response the browser has followed through a redirect, to `url`. */
function followed(url: string, body = 'page'): Response {
    const response = new Response(body, { status: 200 });

    Object.defineProperty(response, 'redirected', { value: true });
    Object.defineProperty(response, 'url', { value: url });

    return response;
}

describe('shouldStore', () => {
    it('stores a successful page read of the same origin', () => {
        expect(sw.shouldStore(doc('/rounds'), ok(), ORIGIN)).toBe(true);
        expect(sw.shouldStore(inertia('/rounds'), ok(), ORIGIN)).toBe(true);
    });

    it.each([
        ['login', '/login'],
        ['the API', '/api/version'],
        ['the health check', '/up'],
        ['the photo of a round', `/rounds/${ROUND}/photo`],
        ['the photo of an import', `/imports/${ROUND}/photo`],
        ['a compiled asset', '/build/assets/app-Cx1.js'],
        ['the service worker', '/sw.js'],
        ['a static file of public/', '/manifest.webmanifest'],
    ])('never stores %s', (_label, path) => {
        expect(sw.shouldStore(doc(path), ok(), ORIGIN)).toBe(false);
    });

    it('never stores a write', () => {
        const post = new Request(`${ORIGIN}/rounds`, { method: 'POST' });

        expect(sw.shouldStore(post, ok(), ORIGIN)).toBe(false);
    });

    it.each([
        ['a redirect', 302],
        ['an asset version conflict', 409],
        ['a server error', 500],
        ['a missing page', 404],
    ])('never stores %s', (_label, status) => {
        const response = new Response(null, { status });

        expect(sw.shouldStore(doc('/rounds'), response, ORIGIN)).toBe(false);
    });

    it('never stores a response reached through a redirect', () => {
        const response = followed(`${ORIGIN}/rounds`);

        expect(sw.shouldStore(doc('/rounds'), response, ORIGIN)).toBe(false);
    });

    it('never stores another origin', () => {
        const request = new Request('https://fonts.example/rounds');

        expect(sw.shouldStore(request, ok(), ORIGIN)).toBe(false);
    });

    it('never stores a partial reload, which holds only some props', () => {
        const partial = inertia('/import', {
            'X-Inertia-Partial-Component': 'import/index',
            'X-Inertia-Partial-Data': 'imports',
        });

        expect(sw.shouldStore(partial, ok(), ORIGIN)).toBe(false);
    });
});

describe('request routing', () => {
    it('serves /build/* from the assets cache, nothing else', () => {
        expect(sw.isAssetRequest(get('/build/assets/app-Cx1.js'), ORIGIN)).toBe(
            true,
        );
        expect(sw.isAssetRequest(get('/rounds'), ORIGIN)).toBe(false);
        expect(
            sw.isAssetRequest(
                new Request('https://cdn.example/build/assets/app-Cx1.js'),
                ORIGIN,
            ),
        ).toBe(false);
    });

    it('reads pages network first, not the excluded addresses', () => {
        expect(sw.isPageRequest(doc('/strokes'), ORIGIN)).toBe(true);
        expect(sw.isPageRequest(doc('/login'), ORIGIN)).toBe(false);
        expect(sw.isPageRequest(get(`/rounds/${ROUND}/photo`), ORIGIN)).toBe(
            false,
        );
    });
});

describe('cacheKey', () => {
    it('keys a document by its bare address', () => {
        expect(sw.cacheKey(doc('/rounds'))).toBe('https://golf.example/rounds');
    });

    it('keys an Inertia visit apart from the document', () => {
        expect(sw.cacheKey(inertia('/rounds'))).toBe(
            'https://golf.example/rounds?__sw=inertia',
        );
    });

    it('keeps the query and drops the fragment', () => {
        expect(sw.cacheKey(inertia('/handicaps?joueur=gab#liste'))).toBe(
            'https://golf.example/handicaps?joueur=gab&__sw=inertia',
        );
        expect(sw.cacheKey(doc('/handicaps?joueur=gab#liste'))).toBe(
            'https://golf.example/handicaps?joueur=gab',
        );
    });
});

describe('clearsPages', () => {
    it('clears on the response of POST /logout', async () => {
        const logout = new Request(`${ORIGIN}/logout`, { method: 'POST' });

        await expect(sw.clearsPages(logout, ok(), ORIGIN)).resolves.toBe(true);
    });

    it('clears on an Inertia visit redirected to /login', async () => {
        await expect(
            sw.clearsPages(
                inertia('/rounds'),
                followed(`${ORIGIN}/login`),
                ORIGIN,
            ),
        ).resolves.toBe(true);
    });

    it('clears on a 409 whose X-Inertia-Location is /login', async () => {
        const response = new Response(null, {
            status: 409,
            headers: { 'X-Inertia-Location': `${ORIGIN}/login` },
        });

        await expect(
            sw.clearsPages(inertia('/rounds'), response, ORIGIN),
        ).resolves.toBe(true);
    });

    it('clears on a 401 Session requise', async () => {
        const response = new Response('{"error":"Session requise"}', {
            status: 401,
            headers: { 'Content-Type': 'application/json' },
        });
        const upload = new Request(`${ORIGIN}/imports`, { method: 'POST' });

        await expect(sw.clearsPages(upload, response, ORIGIN)).resolves.toBe(
            true,
        );
    });

    it('clears on the login page served to a document', async () => {
        // A document redirect reaches the worker as an opaque redirect: the
        // login page the browser lands on is the only readable sign, and
        // GET /login answers 200 only without a session.
        await expect(sw.clearsPages(doc('/login'), ok(), ORIGIN)).resolves.toBe(
            true,
        );
    });

    it('keeps the pages on an ordinary read', async () => {
        await expect(
            sw.clearsPages(doc('/rounds'), ok(), ORIGIN),
        ).resolves.toBe(false);
    });

    it('keeps the pages on a 409 that reloads after a deploy', async () => {
        const response = new Response(null, {
            status: 409,
            headers: { 'X-Inertia-Location': `${ORIGIN}/rounds` },
        });

        await expect(
            sw.clearsPages(inertia('/rounds'), response, ORIGIN),
        ).resolves.toBe(false);
    });

    it('keeps the pages on a 401 for another reason', async () => {
        const response = new Response('{"error":"Jeton refusé"}', {
            status: 401,
            headers: { 'Content-Type': 'application/json' },
        });

        await expect(
            sw.clearsPages(get('/api/version'), response, ORIGIN),
        ).resolves.toBe(false);
    });
});

describe('offline answers', () => {
    it('answers a document without a copy with /offline.html', async () => {
        const offlinePage = new Response('<p>Hors ligne</p>', {
            headers: { 'Content-Type': 'text/html' },
        });

        const response = sw.offlineFallback(doc('/courses'), offlinePage);

        expect(response.status).toBe(200);
        await expect(response.text()).resolves.toBe('<p>Hors ligne</p>');
    });

    it('answers an Inertia visit without a copy with 503 X-Offline: miss', () => {
        const offlinePage = new Response('<p>Hors ligne</p>');

        const response = sw.offlineFallback(inertia('/courses'), offlinePage);

        expect(response.status).toBe(503);
        expect(response.headers.get('X-Offline')).toBe('miss');
    });

    it('stamps the stored copy with the instant of the copy', async () => {
        const response = new Response('{"component":"rounds/index"}', {
            status: 200,
            headers: { 'X-Inertia': 'true', Vary: 'X-Inertia' },
        });

        const copy = sw.storedCopy(response, new Date('2026-10-31T01:04:00Z'));

        expect(copy.status).toBe(200);
        expect(copy.headers.get('X-Offline-Stored-At')).toBe(
            '2026-10-31T01:04:00.000Z',
        );
        expect(copy.headers.get('X-Inertia')).toBe('true');
        await expect(copy.text()).resolves.toBe('{"component":"rounds/index"}');
    });

    it('marks the copy it serves with X-Offline: hit', async () => {
        const cached = new Response('<html></html>', {
            headers: { 'X-Offline-Stored-At': '2026-10-31T01:04:00.000Z' },
        });

        const served = sw.servedCopy(cached);

        expect(served.headers.get('X-Offline')).toBe('hit');
        expect(served.headers.get('X-Offline-Stored-At')).toBe(
            '2026-10-31T01:04:00.000Z',
        );
        await expect(served.text()).resolves.toBe('<html></html>');
    });

    it('tells the pages which copy it served', () => {
        const cached = new Response('{}', {
            headers: { 'X-Offline-Stored-At': '2026-10-31T01:04:00.000Z' },
        });

        expect(
            sw.servedFromCacheMessage(inertia('/rounds#top'), cached),
        ).toEqual({
            type: 'served-from-cache',
            url: 'https://golf.example/rounds',
            storedAt: '2026-10-31T01:04:00.000Z',
        });
    });
});

describe('importRequests', () => {
    it('asks for /import in both forms, under their two keys', () => {
        const [page, visit] = sw.importRequests(ORIGIN, 'a1b2c3');

        expect(sw.cacheKey(page)).toBe('https://golf.example/import');
        expect(page.headers.get('Accept')).toBe(
            'text/html,application/xhtml+xml',
        );
        expect(sw.cacheKey(visit)).toBe(
            'https://golf.example/import?__sw=inertia',
        );
        expect(visit.headers.get('X-Inertia')).toBe('true');
        expect(visit.headers.get('X-Inertia-Version')).toBe('a1b2c3');
        expect(visit.headers.get('X-Requested-With')).toBe('XMLHttpRequest');
    });
});

describe('trimCache', () => {
    const cacheOf = (count: number) => {
        const keys = Array.from(
            { length: count },
            (_, index) => new Request(`${ORIGIN}/entry-${index}`),
        );
        const deleted: string[] = [];
        const cache: Cache = {
            keys: () => Promise.resolve(keys),
            delete: vi.fn((request: Request) => {
                deleted.push(new URL(request.url).pathname);

                return Promise.resolve(true);
            }),
        };

        return { cache, deleted };
    };

    it('keeps 300 assets', async () => {
        const { cache, deleted } = cacheOf(300);

        await sw.trimCache(cache, 'assets');

        expect(deleted).toEqual([]);
    });

    it('drops the oldest asset beyond 300', async () => {
        const { cache, deleted } = cacheOf(302);

        await sw.trimCache(cache, 'assets');

        expect(deleted).toEqual(['/entry-0', '/entry-1']);
    });

    it('keeps 100 pages', async () => {
        const { cache, deleted } = cacheOf(100);

        await sw.trimCache(cache, 'pages');

        expect(deleted).toEqual([]);
    });

    it('drops the oldest page beyond 100', async () => {
        const { cache, deleted } = cacheOf(101);

        await sw.trimCache(cache, 'pages');

        expect(deleted).toEqual(['/entry-0']);
    });
});
