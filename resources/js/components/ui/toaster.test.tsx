import {
    act,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/react';
import { toast } from 'sonner';
import { afterEach, describe, expect, it } from 'vite-plus/test';

import { Toaster } from '@/components/ui/toaster';
import { showFlashToast } from '@/lib/flash-toast';

// Spec app-shell « Message éphémère » (decision Q7): a confirmation in the
// light pill with the check, announced politely (role="status"); an error in
// the red pill without check, announced at once (role="alert"); a tap on the
// pill or its « Fermer le message » button closes it.
// Mutations: the error rendered with the confirmation style (bg-ink and check)
// → the error test goes red; roles swapped (status for an error) → both role
// tests go red; the pill's onClick removed → the tap test goes red.

afterEach(() => {
    act(() => {
        toast.dismiss();
    });
});

function pillOf(element: HTMLElement) {
    return element.closest<HTMLElement>('[data-slot="toast-pill"]')!;
}

function show(type: 'error' | 'success', message: string) {
    act(() => {
        showFlashToast({ toast: { type, message } });
    });
}

describe('Toaster with the flash toast', () => {
    it('shows a confirmation in the light pill with the check', async () => {
        render(<Toaster />);
        show('success', 'Round enregistré.');

        const status = await screen.findByRole('status');

        expect(status.textContent).toBe('Round enregistré.');
        expect(screen.queryByRole('alert')).toBeNull();
        expect(pillOf(status)).toHaveClass('bg-ink', 'text-bg', 'rounded-pill');
        expect(pillOf(status).querySelector('svg.lucide-check')).not.toBeNull();
    });

    it('shows an error in the red pill, without check', async () => {
        render(<Toaster />);
        show('error', 'Ce joueur existe déjà.');

        const alert = await screen.findByRole('alert');

        expect(alert.textContent).toBe('Ce joueur existe déjà.');
        expect(screen.queryByRole('status')).toBeNull();
        expect(pillOf(alert)).toHaveClass('bg-signal', 'text-bg');
        expect(pillOf(alert)).not.toHaveClass('bg-ink');
        expect(pillOf(alert).querySelector('svg.lucide-check')).toBeNull();
    });

    it('shows nothing for a flash without toast', () => {
        render(<Toaster />);

        act(() => {
            showFlashToast({});
        });

        expect(
            document.querySelectorAll('[data-slot="toast-pill"]'),
        ).toHaveLength(0);
    });

    it('closes the pill on a tap', async () => {
        render(<Toaster />);
        show('error', 'Ce joueur existe déjà.');

        fireEvent.click(pillOf(await screen.findByRole('alert')));

        await waitFor(() => {
            expect(screen.queryByText('Ce joueur existe déjà.')).toBeNull();
        });
    });

    it('closes the pill with the « Fermer le message » button', async () => {
        render(<Toaster />);
        show('success', 'Round enregistré.');

        fireEvent.click(
            await screen.findByRole('button', { name: 'Fermer le message' }),
        );

        await waitFor(() => {
            expect(screen.queryByText('Round enregistré.')).toBeNull();
        });
    });
});
