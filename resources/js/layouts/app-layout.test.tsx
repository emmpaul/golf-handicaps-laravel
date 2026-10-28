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

import AppLayout from '@/layouts/app-layout';

// Spec app-shell « Message éphémère »: the layout subscribes to Inertia's
// `flash` event and shows the toast. router.on is simulated and keeps the
// handler it receives; usePage is simulated for the nav and the version line.
// Mutation: the `router.on('flash', …)` subscription removed from the layout
// → no handler is kept and both tests go red.

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
            url: '/',
            props: { app: { version: '0.6.0', commit: null } },
        }),
        router: {
            on: (type: string, handler: typeof subscription.handler) => {
                if (type === 'flash') {
                    subscription.handler = handler;
                }

                return subscription.unsubscribe;
            },
        },
    };
});

beforeEach(() => {
    subscription.handler = undefined;
    subscription.unsubscribe.mockClear();
});

afterEach(() => {
    act(() => {
        toast.dismiss();
    });
});

describe('AppLayout', () => {
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

        expect((await screen.findByRole('alert')).textContent).toBe(
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
