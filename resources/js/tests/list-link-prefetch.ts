import { router } from '@inertiajs/react';
import { fireEvent, waitFor } from '@testing-library/react';
import { expect, vi } from 'vite-plus/test';

/**
 * Spec app-shell « Liens de liste préchargés » (design D5): a list link
 * prefetches its page when a finger touches it, before the release, and when
 * the pointer rests on it, both with the freshness rule of the sections. The
 * real Link runs; only `router.prefetch` is replaced, so no request leaves.
 * Returns the calls of the touch, then those of the hover, each as
 * `[href, visit options, cache options]`.
 */
async function listLinkPrefetches(
    link: HTMLElement,
): Promise<{ touch: unknown[][]; hover: unknown[][] }> {
    const prefetch = vi.spyOn(router, 'prefetch').mockImplementation(() => {});

    try {
        fireEvent.touchStart(link);
        const touch = prefetch.mock.calls.map(([href, params, options]) => [
            href,
            params,
            options,
        ]);
        prefetch.mockClear();

        fireEvent.mouseEnter(link);
        // Inertia waits `prefetch.hoverDelay` (75 ms) on the link first.
        await waitFor(() => expect(prefetch).toHaveBeenCalled());
        const hover = prefetch.mock.calls.map(([href, , options]) => [
            href,
            options,
        ]);
        fireEvent.mouseLeave(link);

        return { touch, hover };
    } finally {
        prefetch.mockRestore();
    }
}

/**
 * The link prefetches `href` on touch, with a GET and the freshness rule of
 * the sections (fresh 30 s, stale up to 5 min), and on hover with the same
 * rule.
 */
export async function expectPrefetchedOnTouchAndHover(
    link: HTMLElement,
    href: string,
): Promise<void> {
    const { touch, hover } = await listLinkPrefetches(link);

    expect(touch).toEqual([
        [href, { method: 'get' }, { cacheFor: ['30s', '5m'] }],
    ]);
    expect(hover).toEqual([[href, { cacheFor: ['30s', '5m'], cacheTags: [] }]]);
}
