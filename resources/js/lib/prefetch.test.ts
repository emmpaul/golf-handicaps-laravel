import { router } from '@inertiajs/react';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';

import { flushPrefetchAfterWrites, listLinkPrefetch } from '@/lib/prefetch';

// Spec app-shell « Données à jour après une écriture » (design D5): a write
// that succeeds forgets every prefetched page; a read, or a write refused by
// validation, keeps them. The listener runs on the real router events: the
// test fires the DOM events Inertia 3.9.1 fires for a visit, in its order
// (`Response.process`): `inertia:start`, `inertia:beforeUpdate` with the new
// page, then the page is rendered, then `inertia:success` or `inertia:error`,
// then `inertia:finish`. The flush must come before the render: the tabs
// prefetch the sections of the new page as soon as it is shown, and a flush on
// `success` would forget those prefetches too (seen in the browser: the tap
// on Handicaps 2 s after deleting a round sent a request).
// Only `router.flushAll` is replaced, to count its calls.
// Mutations: the flush moved to `success` → the « before the new page »
// test goes red; the errors check dropped → the refused POST flushes and its
// test goes red; the method check dropped → the GET test goes red.

const PAGE = { component: 'players/index', url: '/players', props: {} };
const REFUSED = {
    ...PAGE,
    props: { errors: { name: 'Ce nom est déjà pris.' } },
};

const fire = (name: string, detail: object) =>
    document.dispatchEvent(new CustomEvent(`inertia:${name}`, { detail }));

function visit(id: string, method: string, outcome: 'success' | 'error') {
    const pending = { id, method, url: new URL(`http://localhost${PAGE.url}`) };

    fire('start', { visit: pending });

    if (outcome === 'success') {
        fire('beforeUpdate', { page: PAGE });
        fire('success', { page: PAGE, visitId: id });
    } else {
        fire('beforeUpdate', { page: REFUSED });
        fire('error', {
            errors: { name: 'Ce nom est déjà pris.' },
            page: REFUSED,
            visitId: id,
        });
    }

    fire('finish', { visit: pending });
}

describe('prefetch cache after a visit', () => {
    let flushAll: ReturnType<typeof vi.spyOn>;
    let stop: () => void;

    beforeEach(() => {
        flushAll = vi.spyOn(router, 'flushAll').mockImplementation(() => {});
        stop = flushPrefetchAfterWrites();
    });

    afterEach(() => {
        stop();
        flushAll.mockRestore();
    });

    it('flushes everything once after a successful POST', () => {
        visit('visit-post', 'post', 'success');

        expect(flushAll).toHaveBeenCalledTimes(1);
    });

    it('flushes everything once after a successful DELETE', () => {
        visit('visit-delete', 'delete', 'success');

        expect(flushAll).toHaveBeenCalledTimes(1);
    });

    it('keeps the cache after a successful GET', () => {
        visit('visit-get', 'get', 'success');

        expect(flushAll).toHaveBeenCalledTimes(0);
    });

    it('keeps the cache after a POST refused by validation', () => {
        visit('visit-refused', 'post', 'error');

        expect(flushAll).toHaveBeenCalledTimes(0);
    });

    it('flushes before the new page of a write is rendered', () => {
        const pending = {
            id: 'visit-delete',
            method: 'delete',
            url: new URL('http://localhost/rounds/r-1'),
        };

        fire('start', { visit: pending });
        fire('beforeUpdate', { page: { ...PAGE, url: '/rounds' } });

        expect(flushAll).toHaveBeenCalledTimes(1);

        fire('success', { page: PAGE, visitId: 'visit-delete' });
        fire('finish', { visit: pending });

        expect(flushAll).toHaveBeenCalledTimes(1);
    });

    it('keeps the cache for a GET that follows a refused POST', () => {
        visit('visit-refused', 'post', 'error');
        visit('visit-get', 'get', 'success');

        expect(flushAll).toHaveBeenCalledTimes(0);
    });
});

// Spec app-shell « Liens de liste préchargés » (design D5): the props of a
// list link prefetch on hover, and on touch through `router.prefetch`, with
// the freshness rule of the sections. Inertia 3.9.1 binds its `click` mode to
// `mousedown` only, which a touch fires after the release.
// Mutations: `prefetch: 'click'` → the props test goes red; the touch
// prefetching the URL without `method: 'get'` or with another `cacheFor` →
// the touch tests go red.
describe('listLinkPrefetch', () => {
    let prefetch: ReturnType<typeof vi.spyOn>;

    beforeEach(() => {
        prefetch = vi.spyOn(router, 'prefetch').mockImplementation(() => {});
    });

    afterEach(() => {
        prefetch.mockRestore();
    });

    it('prefetches on hover, fresh 30 s then stale up to 5 min', () => {
        const { prefetch: mode, cacheFor } = listLinkPrefetch('/rounds/r-1');

        expect([mode, cacheFor]).toEqual(['hover', ['30s', '5m']]);
    });

    it('prefetches the page with a GET when a finger touches the link', () => {
        listLinkPrefetch('/rounds/r-1').onTouchStart();

        expect(prefetch.mock.calls).toEqual([
            ['/rounds/r-1', { method: 'get' }, { cacheFor: ['30s', '5m'] }],
        ]);
    });

    it('takes the URL of a Wayfinder route', () => {
        listLinkPrefetch({ url: '/imports/i-1', method: 'get' }).onTouchStart();

        expect(prefetch.mock.calls).toEqual([
            ['/imports/i-1', { method: 'get' }, { cacheFor: ['30s', '5m'] }],
        ]);
    });
});
