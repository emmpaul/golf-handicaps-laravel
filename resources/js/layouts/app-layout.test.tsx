import { act, render, screen, within } from '@testing-library/react';
import { toast } from 'sonner';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';

import { PageTopRow } from '@/components/page-top-row';
import AppLayout from '@/layouts/app-layout';

// Spec app-shell « Rangée du haut des pages », « Colonne unique à toute
// largeur », « En-tête net sous la barre d'état », « Navigation masquée
// pendant l'édition », « Navigation entre sections sur téléphone » (bas de
// page atteignable), « Message éphémère »; visual-theme « Marque d'app dans
// l'en-tête »; design D3, D4, D5. router.on is simulated and keeps the flash
// handler it receives; usePage is simulated for the nav, the top row and the
// version line.
// Mutations: BrandMark kept beside « Golf » in the top row → the « no drawing »
// test goes red; AppNav rendered in editor mode → the editor test goes red;
// version line removed from main → the « last element » test goes red; the
// `router.on('flash', …)` subscription removed → both flash tests go red.

const page = vi.hoisted(() => ({ url: '/' }));

const subscription = vi.hoisted(() => ({
    handler: undefined as
        | ((event: { detail: { flash: unknown } }) => void)
        | undefined,
    unsubscribe: vi.fn(),
}));

vi.mock('@inertiajs/react', async (importOriginal) => {
    const inertia = await importOriginal<typeof import('@inertiajs/react')>();

    return {
        ...inertia,
        usePage: () => ({
            url: page.url,
            props: { app: { version: '0.6.0', commit: null } },
        }),
        router: {
            on: (type: string, handler: typeof subscription.handler) => {
                if (type === 'flash') {
                    subscription.handler = handler;
                }

                return subscription.unsubscribe;
            },
            // The tabs prefetch their page on mount (NavLink): no request.
            prefetch: () => {},
        },
    };
});

beforeEach(() => {
    page.url = '/rounds';
    subscription.handler = undefined;
    subscription.unsubscribe.mockClear();
});

afterEach(() => {
    act(() => {
        toast.dismiss();
    });
});

function topRow() {
    return document.querySelector<HTMLElement>('[data-slot="page-top-row"]')!;
}

describe('PageTopRow, section variant', () => {
    it('links « Golf » to home, with no drawing beside it', () => {
        render(<PageTopRow />);

        expect(
            within(topRow())
                .getByRole('link', { name: 'Golf' })
                .getAttribute('href'),
        ).toBe('/');
        expect(topRow().querySelector('svg, img')).toBeNull();
    });

    it('shows « Golf » without a link on the home page, actions on the right', () => {
        page.url = '/';
        render(<PageTopRow actions={<button>Se déconnecter</button>} />);

        expect(topRow().textContent).toBe('GolfSe déconnecter');
        expect(within(topRow()).queryByRole('link')).toBeNull();
    });
});

describe('PageTopRow, sub-page variant', () => {
    it('shows the named back link instead of « Golf »', () => {
        page.url = '/rounds/0190a6b2-7c3e-7000-8000-000000000001';
        render(<PageTopRow back={{ href: '/rounds', label: 'Rounds' }} />);

        expect(
            within(topRow())
                .getByRole('link', { name: 'Rounds' })
                .getAttribute('href'),
        ).toBe('/rounds');
        expect(topRow().textContent).not.toContain('Golf');
    });
});

describe('AppLayout', () => {
    it('has no sticky header, only the opaque safe-area band', () => {
        render(<AppLayout>Contenu</AppLayout>);

        const band = document.querySelector('[data-slot="safe-area-band"]');

        expect(screen.queryByRole('banner')).toBeNull();
        expect(band?.getAttribute('aria-hidden')).toBe('true');
        expect(band).toHaveClass('fixed', 'top-0', 'h-(--safe-top)', 'bg-bg');
    });

    it('lays the page out in the 390 px column', () => {
        render(<AppLayout>Contenu</AppLayout>);

        expect(screen.getByRole('main')).toHaveClass(
            'max-w-(--column)',
            'mx-auto',
            'px-(--gutter)',
        );
    });

    it('renders the tab bar and Importer by default', () => {
        render(<AppLayout>Contenu</AppLayout>);

        expect(
            within(
                screen.getByRole('navigation', { name: 'Sections' }),
            ).getByRole('link', { name: 'Importer' }),
        ).toBeInTheDocument();
    });

    it('renders neither the tab bar nor Importer in editor chrome', () => {
        render(<AppLayout chrome="editor">Contenu</AppLayout>);

        expect(screen.queryByRole('navigation')).toBeNull();
        expect(screen.queryByRole('link', { name: 'Importer' })).toBeNull();
    });

    // Spec offline-mode « Bandeau hors ligne », design D5: the banner sits in
    // main, and the top row of the page is ordered before it, so it shows
    // under the row on every page. Mutations: banner not mounted → red; the
    // `-order-1` of the top row dropped (banner above the row) → red.
    it('mounts the offline banner in main, shown under the top row of the page', () => {
        render(
            <AppLayout>
                <PageTopRow />
                <p>Contenu</p>
            </AppLayout>,
        );

        const main = screen.getByRole('main');
        const banner = main.querySelector('[data-slot="offline-banner"]');

        expect(banner?.parentElement).toBe(main);
        expect(banner?.getAttribute('role')).toBe('status');
        expect(main).toHaveClass('*:data-[slot=page-top-row]:-order-1');
    });

    it('keeps the version line as the last element of main', () => {
        render(
            <AppLayout>
                <p>Contenu</p>
            </AppLayout>,
        );

        expect(screen.getByRole('main').lastElementChild?.textContent).toBe(
            'v0.6.0',
        );
    });

    it('shows the toast of a flash event', async () => {
        render(<AppLayout>Contenu</AppLayout>);

        act(() => {
            subscription.handler?.({
                detail: {
                    flash: {
                        toast: {
                            type: 'error',
                            message: 'Ce joueur existe déjà.',
                        },
                    },
                },
            });
        });

        expect((await screen.findByRole('alert')).textContent).toContain(
            'Ce joueur existe déjà.',
        );
    });

    it('removes the flash subscription on unmount', () => {
        const { unmount } = render(<AppLayout>Contenu</AppLayout>);

        expect(subscription.handler).toBeTypeOf('function');
        expect(subscription.unsubscribe).not.toHaveBeenCalled();

        unmount();

        expect(subscription.unsubscribe).toHaveBeenCalledTimes(1);
    });
});
