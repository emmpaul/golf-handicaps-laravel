import { router } from '@inertiajs/react';
import { render, screen, waitFor, within } from '@testing-library/react';
import { createContext, use } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';

import { AppNav } from '@/components/app-nav';

// Spec app-shell « Navigation entre sections sur téléphone », « Adresses des
// sections », « Section courante indiquée », « Section courante visible sur la
// barre d'onglets » and « Bouton Importer » (design D4). Only usePage is
// simulated.
// Mutations: Importer put back first in the bar → the order test goes red;
// label visible on every tab (current classes on all) → the label visibility
// test goes red; badge rendered at 0 → the « no badge » test goes red.

let url = '/';
let pendingImports: number | undefined;

// The page as Inertia gives it through its page context: each page shown is a
// new object, and a visit re-renders every reader of the context, even under
// the React Compiler, which keeps the elements of an unchanged parent. Tests
// that move between pages provide it; the others read `url`.
type TestPage = { url: string; props: { pendingImports?: number } };
const PageShown = createContext<TestPage | null>(null);

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    usePage: () => use(PageShown) ?? { url, props: { pendingImports } },
}));

// Every render prefetches the sections on mount: no request leaves the tests.
const prefetch = vi.spyOn(router, 'prefetch').mockImplementation(() => {});

function bar() {
    return screen.getByRole('navigation', { name: 'Sections' });
}

function links() {
    return within(bar()).getAllByRole('link');
}

function importer() {
    return within(bar()).getByRole('link', { name: 'Importer' });
}

function label(link: HTMLElement) {
    return link.querySelector('[data-slot="tab-label"]');
}

describe('AppNav on a round sub-page', () => {
    beforeEach(() => {
        url = '/rounds/0190a6b2-7c3e-7000-8000-000000000001';
        pendingImports = undefined;
        render(<AppNav />);
    });

    it('lists the four tabs then Importer, in this order', () => {
        expect(
            links().map((link) => [
                link.getAttribute('aria-label'),
                link.getAttribute('href'),
            ]),
        ).toEqual([
            ['Rounds', '/rounds'],
            ['Handicaps', '/handicaps'],
            ['Parcours', '/courses'],
            ['Joueurs', '/players'],
            ['Importer', '/import'],
        ]);
    });

    it('marks only Rounds as current', () => {
        expect(
            links()
                .filter((link) => link.getAttribute('aria-current') === 'page')
                .map((link) => link.getAttribute('aria-label')),
        ).toEqual(['Rounds']);
    });

    it('shows the label of the current tab only, closed to max-width 0 on the others', () => {
        const tabs = links().slice(0, 4);

        expect(
            tabs.map((tab) => [
                tab.getAttribute('aria-label'),
                label(tab)?.textContent,
                label(tab)?.classList.contains('max-w-[90px]'),
                label(tab)?.classList.contains('max-w-0'),
            ]),
        ).toEqual([
            ['Rounds', 'Rounds', true, false],
            ['Handicaps', 'Handicaps', false, true],
            ['Parcours', 'Parcours', false, true],
            ['Joueurs', 'Joueurs', false, true],
        ]);
    });

    it('keeps the accessible name of a tab whose label is closed', () => {
        expect(
            within(bar())
                .getByRole('link', { name: 'Handicaps' })
                .getAttribute('href'),
        ).toBe('/handicaps');
    });

    it('shows no badge on Importer without the count (no session)', () => {
        expect(
            importer().querySelector('[data-slot="pending-badge"]'),
        ).toBeNull();
    });
});

describe('AppNav on /import', () => {
    it('marks Importer as current and no tab', () => {
        url = '/import';
        pendingImports = 0;
        render(<AppNav />);

        expect(
            links()
                .filter((link) => link.getAttribute('aria-current') === 'page')
                .map((link) => link.getAttribute('aria-label')),
        ).toEqual(['Importer']);
    });
});

describe('AppNav pending badge', () => {
    it('shows « 1 » on Importer with one import to check', () => {
        url = '/rounds';
        pendingImports = 1;
        render(<AppNav />);

        expect(
            importer().querySelector('[data-slot="pending-badge"]')
                ?.textContent,
        ).toBe('1');
    });

    it('shows no badge with no import to check', () => {
        url = '/rounds';
        pendingImports = 0;
        render(<AppNav />);

        expect(
            importer().querySelector('[data-slot="pending-badge"]'),
        ).toBeNull();
    });
});

// Spec app-shell « Section courante indiquée », scenarios « Parcours » and
// « Coups »; « Plus d'onglet Coups ».
// Mutations: `'/rounds': ['/courses']` kept in SECTION_PREFIXES → /courses
// marks Rounds too, red; `'/handicaps': ['/strokes']` dropped → /strokes marks
// nothing, red; the Coups tab kept in TABS → the /strokes link test goes red.
describe('AppNav current section', () => {
    it.each([
        ['/courses', ['Parcours']],
        ['/strokes', ['Handicaps']],
        ['/coursesx', []],
    ])('on %s marks %j', (path, expected) => {
        url = path;
        pendingImports = 0;
        render(<AppNav />);

        expect(
            links()
                .filter((link) => link.getAttribute('aria-current') === 'page')
                .map((link) => link.getAttribute('aria-label')),
        ).toEqual(expected);
    });

    it.each(['/rounds', '/handicaps', '/courses', '/players', '/strokes'])(
        'has no link to /strokes nor named « Coups » on %s',
        (path) => {
            url = path;
            pendingImports = 0;
            render(<AppNav />);

            expect(
                links().filter(
                    (link) =>
                        link.getAttribute('href') === '/strokes' ||
                        link.getAttribute('aria-label') === 'Coups',
                ),
            ).toEqual([]);
        },
    );
});

describe('AppNav on the home page', () => {
    it('marks no link as current', () => {
        url = '/';
        pendingImports = 0;
        render(<AppNav />);

        expect(
            links().filter(
                (link) => link.getAttribute('aria-current') === 'page',
            ),
        ).toEqual([]);
    });
});

// B2, DO34: spec « Toutes les sections visibles à 320 px ». jsdom lays out
// nothing, so the width of the bar is summed from the spacing classes of the
// rendered tabs (Tailwind's 4 px unit, arbitrary `[7px]` values) and the
// widths of the labels measured in Chrome (Geist 600, 13 px): Rounds 47,
// Handicaps 66, Parcours 56, Joueurs 51 px. Below 375 px the `max-[375px]:`
// classes apply. The bar must fit 320 − 2 × 16 = 288 px with any label open,
// and 390 px keeps the measures of the design (gap 10, pill padding 7, tabs
// 12 px and current 14 px padding, label 8 px from the icon).
// Mutations: the compact padding of a closed tab at `px-2.5` (or no
// compact classes at all) → the /handicaps sum reaches 300 px, red; the
// design padding of a closed tab changed to `px-2.5` → the 390 px test reads
// 20 instead of 24, red.
const LABEL_WIDTHS: Record<string, number> = {
    Rounds: 47,
    Handicaps: 66,
    Parcours: 56,
    Joueurs: 51,
};

const NARROW = 'max-[375px]:';

/** The px value of a spacing utility (`px`, `ml`, `gap`, `size`), compact or design. */
function spacing(element: Element, utility: string, narrow: boolean): number {
    const classes = [...element.classList];
    const pick = (prefix: string) =>
        classes
            .filter((name) => name.startsWith(`${prefix}${utility}-`))
            .map((name) => name.slice(prefix.length + utility.length + 1))
            .at(-1);
    const value = (narrow ? pick(NARROW) : undefined) ?? pick('');

    if (value === undefined) {
        return 0;
    }

    const arbitrary = /^\[(\d+)px\]$/.exec(value);

    return arbitrary ? Number(arbitrary[1]) : Number(value) * 4;
}

/** Width of the whole bar, its current tab showing its label. */
function barWidth(narrow: boolean): number {
    const nav = bar();
    const list = nav.querySelector('ul')!;
    const tabs = [...list.querySelectorAll('a')].map((tab) => {
        const name = tab.getAttribute('aria-label')!;
        const open = tab.getAttribute('aria-current') === 'page';
        const icon = tab.querySelector('svg')!;
        const text = label(tab)!;

        return (
            2 * spacing(tab, 'px', narrow) +
            spacing(icon, 'size', narrow) +
            (open ? spacing(text, 'ml', narrow) + LABEL_WIDTHS[name] : 0)
        );
    });

    return (
        spacing(importer(), 'size', narrow) +
        spacing(nav, 'gap', narrow) +
        2 + // border of the pill
        2 * spacing(list, 'px', narrow) +
        tabs.reduce((sum, width) => sum + width, 0)
    );
}

describe('AppNav width', () => {
    it.each(['/rounds', '/handicaps', '/courses', '/players'])(
        'fits 288 px at 320 px wide on %s, its label open',
        (path) => {
            url = path;
            pendingImports = 3;
            render(<AppNav />);

            expect(barWidth(true)).toBeLessThanOrEqual(288);
        },
    );

    it('keeps the measures of the design at 390 px on /handicaps', () => {
        url = '/handicaps';
        pendingImports = undefined;
        render(<AppNav />);

        const tabs = links().slice(0, 4);

        expect(spacing(bar(), 'gap', false)).toBe(10);
        expect(spacing(bar().querySelector('ul')!, 'px', false)).toBe(7);
        expect(tabs.map((tab) => spacing(tab, 'px', false))).toEqual([
            12, 14, 12, 12,
        ]);
        expect(spacing(label(tabs[1])!, 'ml', false)).toBe(8);
        expect(barWidth(false)).toBe(340);
    });
});

// Spec app-shell « Sections préchargées » (design D5): every tab and Importer
// prefetch their page on mount, fresh 30 s then stale up to 5 min, except the
// link of the page shown. NavLink runs; only `router.prefetch` is replaced,
// so no request leaves.
// Mutation: prefetch also on the link of the current page → the /players and
// /import tests see their own URL and go red; `cacheFor` changed → every test
// goes red.
describe('AppNav prefetch', () => {
    beforeEach(async () => {
        // Let the mount prefetches queued by earlier tests run before counting.
        await new Promise((resolve) => setTimeout(resolve, 0));
        prefetch.mockClear();
        pendingImports = 0;
    });

    async function prefetched() {
        await waitFor(() => expect(prefetch).toHaveBeenCalled());
        // Every mount prefetch is queued in the same tick: let them all run.
        await new Promise((resolve) => setTimeout(resolve, 0));

        return prefetch.mock.calls.map(([href, params, options]) => [
            href,
            params,
            options,
        ]);
    }

    const GET = { method: 'get' };
    const FRESH_THEN_STALE = { cacheFor: ['30s', '5m'] };

    it('prefetches the four sections and Importer on a round sub-page', async () => {
        url = '/rounds/0190a6b2-7c3e-7000-8000-000000000001';
        render(<AppNav />);

        expect(await prefetched()).toEqual([
            ['/rounds', GET, FRESH_THEN_STALE],
            ['/handicaps', GET, FRESH_THEN_STALE],
            ['/courses', GET, FRESH_THEN_STALE],
            ['/players', GET, FRESH_THEN_STALE],
            ['/import', GET, FRESH_THEN_STALE],
        ]);
    });

    it('does not prefetch /players on /players', async () => {
        url = '/players';
        render(<AppNav />);

        expect(await prefetched()).toEqual([
            ['/rounds', GET, FRESH_THEN_STALE],
            ['/handicaps', GET, FRESH_THEN_STALE],
            ['/courses', GET, FRESH_THEN_STALE],
            ['/import', GET, FRESH_THEN_STALE],
        ]);
    });

    it('does not prefetch /import on /import', async () => {
        url = '/import';
        render(<AppNav />);

        expect(await prefetched()).toEqual([
            ['/rounds', GET, FRESH_THEN_STALE],
            ['/handicaps', GET, FRESH_THEN_STALE],
            ['/courses', GET, FRESH_THEN_STALE],
            ['/players', GET, FRESH_THEN_STALE],
        ]);
    });
});

// Spec app-shell « Sections préchargées »: the tabs prefetch their page each
// time a page is shown. The bar stays mounted from one page to the next, so a
// tab that stops being the current page, and every tab after a write that
// flushed the cache then redirected (to another page or the same one), must
// prefetch again; the hooks of the real Link keep the same dependencies
// between renders (React logs « The final argument passed to useEffect
// changed size between renders » otherwise). A prefetch of a page still fresh
// in the cache sends no request (Inertia core, `PrefetchedRequests.add`).
// Mutations: `prefetch={onOwnPage ? false : 'mount'}` put back on the Link →
// React logs the size warning and the console test goes red; the prefetch
// effect of NavLink keyed on `href` and `onOwnPage` only (not on the page
// shown) → the /rounds/<id> test sees only /rounds and the redirect test sees
// nothing, both red.
describe('AppNav from one page to the next', () => {
    beforeEach(async () => {
        await new Promise((resolve) => setTimeout(resolve, 0));
        prefetch.mockClear();
        pendingImports = 0;
    });

    async function settle() {
        await new Promise((resolve) => setTimeout(resolve, 0));
    }

    /** A new page shown at `path`, as after a visit. */
    function onPage(path: string) {
        return (
            <PageShown value={{ url: path, props: { pendingImports: 0 } }}>
                <AppNav />
            </PageShown>
        );
    }

    async function prefetchedAfterMove(from: string, to: string) {
        const { rerender } = render(onPage(from));
        await settle();
        prefetch.mockClear();

        rerender(onPage(to));
        await settle();

        return prefetch.mock.calls.map(([href]) => href);
    }

    it('prefetches every other section, /players included, from /players to /rounds', async () => {
        expect(await prefetchedAfterMove('/players', '/rounds')).toEqual([
            '/handicaps',
            '/courses',
            '/players',
            '/import',
        ]);
    });

    it('prefetches the five sections again from /rounds to a round', async () => {
        expect(
            await prefetchedAfterMove(
                '/rounds',
                '/rounds/0190a6b2-7c3e-7000-8000-000000000001',
            ),
        ).toEqual(['/rounds', '/handicaps', '/courses', '/players', '/import']);
    });

    it('prefetches again when a write redirects back to the same page', async () => {
        expect(await prefetchedAfterMove('/players', '/players')).toEqual([
            '/rounds',
            '/handicaps',
            '/courses',
            '/import',
        ]);
    });

    it('does not prefetch again on a render of the same page', async () => {
        const page = { url: '/players', props: { pendingImports: 0 } };
        const { rerender } = render(
            <PageShown value={page}>
                <AppNav />
            </PageShown>,
        );
        await settle();
        prefetch.mockClear();

        rerender(
            <PageShown value={page}>
                <AppNav />
            </PageShown>,
        );
        await settle();

        expect(prefetch.mock.calls).toEqual([]);
    });

    it('logs no React error on the move', async () => {
        const error = vi.spyOn(console, 'error');

        try {
            await prefetchedAfterMove('/players', '/rounds');

            expect(error.mock.calls).toEqual([]);
        } finally {
            error.mockRestore();
        }
    });
});
