// Service worker of the offline mode (offline-pwa, design D1 to D3 and D6).
//
// A classic script with no build step, served by Caddy at the root with
// Cache-Control: no-cache, so its scope is / and a deploy replaces it at the
// next visit. It keeps copies only, with no state to migrate: skipWaiting() and
// clients.claim() take over at once.
//
// - Compiled assets (/build/*): cache first, in the « assets » cache filled on
//   demand, bounded to 300 entries. Their names carry a hash: a copy is never
//   stale.
// - Pages: network first. A 200 read with a session is stored in the « pages »
//   cache (bounded to 100), stamped with X-Offline-Stored-At. Without network,
//   the stored copy is served with X-Offline: hit and the pages are told by the
//   message { type: 'served-from-cache', url, storedAt }; with no copy, a
//   document gets /offline.html and an Inertia visit a 503 X-Offline: miss.
// - The « pages » cache is emptied by the response of POST /logout and by any
//   response that refuses the session (redirect to /login, 409 whose
//   X-Inertia-Location is /login, 401 « Session requise », login page served).
// - The page posts { type: 'cache-import', version } to keep /import in both
//   forms even before a visit (design D2).
//
// Under Vitest `self.registration` is undefined: the script installs no
// listener and exposes its decision functions on globalThis.offlineServiceWorker
// (resources/js/tests/service-worker.test.ts).

const ASSETS = 'assets';
const PAGES = 'pages';
const OFFLINE = 'offline';
const OFFLINE_PAGE = '/offline.html';
const LIMITS = { [ASSETS]: 300, [PAGES]: 100 };
const ROUND_OR_IMPORT_PHOTO = /^\/(rounds|imports)\/[^/]+\/photo$/;

/** @param {Request} request */
function isInertia(request) {
    return request.headers.get('X-Inertia') === 'true';
}

/**
 * @param {Request} request
 * @param {string} origin
 */
function sameOriginGet(request, origin) {
    return request.method === 'GET' && new URL(request.url).origin === origin;
}

/**
 * A compiled asset, served cache first.
 *
 * @param {Request} request
 * @param {string} origin
 */
function isAssetRequest(request, origin) {
    return (
        sameOriginGet(request, origin) &&
        new URL(request.url).pathname.startsWith('/build/')
    );
}

/**
 * A page of the application, read network first. Static files of public/
 * (a dot in the last segment), compiled assets, the login page, the API, the
 * health check and the photos of rounds and imports are not pages.
 *
 * @param {Request} request
 * @param {string} origin
 */
function isPageRequest(request, origin) {
    if (!sameOriginGet(request, origin)) {
        return false;
    }

    const path = new URL(request.url).pathname;
    const lastSegment = path.slice(path.lastIndexOf('/') + 1);

    return !(
        path.startsWith('/build/') ||
        path === '/login' ||
        path.startsWith('/api/') ||
        path === '/up' ||
        ROUND_OR_IMPORT_PHOTO.test(path) ||
        lastSegment.includes('.')
    );
}

/**
 * Whether the network response of a page read is kept: a full 200 that was
 * not reached through a redirect. A partial reload holds only some props.
 *
 * @param {Request} request
 * @param {Response} response
 * @param {string} origin
 */
function shouldStore(request, response, origin) {
    return (
        isPageRequest(request, origin) &&
        response.status === 200 &&
        !response.redirected &&
        !request.headers.has('X-Inertia-Partial-Data') &&
        !request.headers.has('X-Inertia-Partial-Except')
    );
}

/**
 * The key of a page in the « pages » cache: an address has two answers, the
 * HTML document and the Inertia JSON, so the form is added to the address.
 *
 * @param {Request} request
 */
function cacheKey(request) {
    const url = new URL(request.url);

    url.hash = '';

    if (!isInertia(request)) {
        return url.href;
    }

    return `${url.href}${url.search ? '&' : '?'}__sw=inertia`;
}

/**
 * @param {string | null} value
 * @param {string} origin
 */
function isLogin(value, origin) {
    if (!value) {
        return false;
    }

    const url = new URL(value, origin);

    return url.origin === origin && url.pathname === '/login';
}

/**
 * Whether a network response closes the session of this browser, so the
 * stored pages must go.
 *
 * @param {Request} request
 * @param {Response} response
 * @param {string} origin
 * @returns {Promise<boolean>}
 */
async function clearsPages(request, response, origin) {
    const url = new URL(request.url);

    if (url.origin !== origin) {
        return false;
    }

    if (request.method === 'POST' && url.pathname === '/logout') {
        return true;
    }

    // A document redirect reaches the worker as an opaque redirect; GET /login
    // answers 200 only to a browser without a session.
    if (
        request.method === 'GET' &&
        url.pathname === '/login' &&
        response.status === 200 &&
        !response.redirected
    ) {
        return true;
    }

    if (response.redirected && isLogin(response.url, origin)) {
        return true;
    }

    if (
        response.status === 409 &&
        isLogin(response.headers.get('X-Inertia-Location'), origin)
    ) {
        return true;
    }

    if (response.status === 401) {
        try {
            const body = await response.clone().json();

            return body?.error === 'Session requise';
        } catch {
            return false;
        }
    }

    return false;
}

/**
 * The answer to a page read without network and without a copy.
 *
 * @param {Request} request
 * @param {Response | undefined} offlinePage
 */
function offlineFallback(request, offlinePage) {
    if (isInertia(request) || !offlinePage) {
        return new Response(null, {
            status: 503,
            headers: { 'X-Offline': 'miss' },
        });
    }

    return offlinePage;
}

/**
 * @param {Response} response
 * @param {Record<string, string>} extra
 */
function withHeaders(response, extra) {
    const headers = new Headers(response.headers);

    for (const [name, value] of Object.entries(extra)) {
        headers.set(name, value);
    }

    return new Response(response.clone().body, {
        status: response.status,
        statusText: response.statusText,
        headers,
    });
}

/**
 * The copy kept in the « pages » cache, stamped with its instant.
 *
 * @param {Response} response
 * @param {Date} now
 */
function storedCopy(response, now) {
    return withHeaders(response, { 'X-Offline-Stored-At': now.toISOString() });
}

/** @param {Response} cached */
function servedCopy(cached) {
    return withHeaders(cached, { 'X-Offline': 'hit' });
}

/**
 * @param {Request} request
 * @param {Response} cached
 */
function servedFromCacheMessage(request, cached) {
    const url = new URL(request.url);

    url.hash = '';

    return {
        type: 'served-from-cache',
        url: url.href,
        storedAt: cached.headers.get('X-Offline-Stored-At'),
    };
}

/**
 * The two forms of /import kept at the first opening with a session.
 *
 * @param {string} origin
 * @param {string} version the Inertia asset version of the page
 */
function importRequests(origin, version) {
    return [
        new Request(`${origin}/import`, {
            headers: { Accept: 'text/html,application/xhtml+xml' },
        }),
        new Request(`${origin}/import`, {
            headers: {
                Accept: 'text/html, application/xhtml+xml',
                'X-Inertia': 'true',
                'X-Inertia-Version': version,
                'X-Requested-With': 'XMLHttpRequest',
            },
        }),
    ];
}

/**
 * Drops the oldest entries beyond the bound of the cache. Cache Storage keeps
 * the keys in write order, and a put moves an entry to the end.
 *
 * @param {{ keys(): Promise<readonly Request[]>, delete(request: Request): Promise<boolean> }} cache
 * @param {string} cacheName
 */
async function trimCache(cache, cacheName) {
    const limit = LIMITS[cacheName] ?? Infinity;
    const keys = await cache.keys();

    for (const key of keys.slice(0, Math.max(0, keys.length - limit))) {
        await cache.delete(key);
    }
}

const logic = {
    isAssetRequest,
    isPageRequest,
    shouldStore,
    cacheKey,
    clearsPages,
    offlineFallback,
    storedCopy,
    servedCopy,
    servedFromCacheMessage,
    importRequests,
    trimCache,
};

/**
 * Applies a network response to the « pages » cache: empties it when the
 * response closes the session, otherwise keeps the page when it qualifies.
 *
 * @param {Request} request
 * @param {Response} response
 * @param {(promise: Promise<unknown>) => void} [waitUntil]
 */
async function record(request, response, waitUntil) {
    const origin = self.location.origin;

    if (await clearsPages(request, response, origin)) {
        await caches.delete(PAGES);

        return;
    }

    if (shouldStore(request, response, origin)) {
        const cache = await caches.open(PAGES);

        await cache.put(cacheKey(request), storedCopy(response, new Date()));

        const trimming = trimCache(cache, PAGES);

        if (waitUntil) {
            waitUntil(trimming);
        } else {
            await trimming;
        }
    }
}

/**
 * Posts the message to the page that asked: the existing page for an Inertia
 * visit, the page being created for a document (it may not exist yet, so a few
 * tries), else every window.
 *
 * @param {FetchEvent} event
 * @param {object} message
 */
async function notify(event, message) {
    const id = event.resultingClientId || event.clientId;

    for (let attempt = 0; id && attempt < 20; attempt++) {
        const client = await self.clients.get(id);

        if (client) {
            client.postMessage(message);

            return;
        }

        await new Promise((resolve) => setTimeout(resolve, 100));
    }

    const windows = await self.clients.matchAll({
        type: 'window',
        includeUncontrolled: true,
    });

    for (const client of windows) {
        client.postMessage(message);
    }
}

/** @param {FetchEvent} event */
async function networkFirst(event) {
    const request = event.request;
    let response;

    try {
        response = await fetch(request);
    } catch {
        const cached = await caches.match(cacheKey(request), {
            cacheName: PAGES,
            ignoreVary: true,
        });

        if (cached) {
            event.waitUntil(
                notify(event, servedFromCacheMessage(request, cached)),
            );

            return servedCopy(cached);
        }

        const offlinePage = await caches.match(OFFLINE_PAGE, {
            cacheName: OFFLINE,
            ignoreVary: true,
        });

        return offlineFallback(request, offlinePage);
    }

    await record(request, response, (promise) => event.waitUntil(promise));

    return response;
}

/** @param {FetchEvent} event */
async function cacheFirst(event) {
    const cache = await caches.open(ASSETS);
    const cached = await cache.match(event.request, { ignoreVary: true });

    if (cached) {
        return cached;
    }

    const response = await fetch(event.request);

    if (response.status === 200) {
        await cache.put(event.request, response.clone());
        event.waitUntil(trimCache(cache, ASSETS));
    }

    return response;
}

/**
 * A request that is neither an asset nor a page (a write, the login page):
 * passed to the network, its response only checked for a closed session. A
 * network failure stays a network failure for the page.
 *
 * @param {FetchEvent} event
 */
async function observe(event) {
    const response = await fetch(event.request);

    await record(event.request, response, (promise) =>
        event.waitUntil(promise),
    );

    return response;
}

/** @param {string} version */
async function cacheImport(version) {
    for (const request of importRequests(self.location.origin, version)) {
        try {
            await record(request, await fetch(request));
        } catch {
            // Offline or refused: the next opening with a session tries again.
        }
    }
}

if (typeof self.registration === 'undefined') {
    globalThis.offlineServiceWorker = logic;
} else {
    self.addEventListener('install', (event) => {
        event.waitUntil(
            caches
                .open(OFFLINE)
                .then((cache) =>
                    cache.add(new Request(OFFLINE_PAGE, { cache: 'reload' })),
                )
                .then(() => self.skipWaiting()),
        );
    });

    self.addEventListener('activate', (event) => {
        const known = [ASSETS, PAGES, OFFLINE];

        event.waitUntil(
            caches
                .keys()
                .then((names) =>
                    Promise.all(
                        names
                            .filter((name) => !known.includes(name))
                            .map((name) => caches.delete(name)),
                    ),
                )
                .then(() => self.clients.claim()),
        );
    });

    self.addEventListener('fetch', (event) => {
        const request = event.request;
        const origin = self.location.origin;
        const url = new URL(request.url);

        if (url.origin !== origin) {
            return;
        }

        if (isAssetRequest(request, origin)) {
            event.respondWith(cacheFirst(event));
        } else if (isPageRequest(request, origin)) {
            event.respondWith(networkFirst(event));
        } else if (request.method !== 'GET' || url.pathname === '/login') {
            event.respondWith(observe(event));
        }
    });

    self.addEventListener('message', (event) => {
        if (event.data?.type === 'cache-import') {
            event.waitUntil(cacheImport(String(event.data.version ?? '')));
        }
    });
}
