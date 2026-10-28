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

// Spec app-shell « Message éphémère »: an error is announced at once
// (role="alert"), a confirmation politely (role="status"), and the close
// button removes the message.
// Mutation: `toast.success` for an error in showFlashToast → the error test
// goes red (the message loses the destructive colour of `classNames.error`).

afterEach(() => {
    act(() => {
        toast.dismiss();
    });
});

describe('Toaster with the flash toast', () => {
    it('shows an error in an alert', async () => {
        render(<Toaster />);

        act(() => {
            showFlashToast({
                toast: { type: 'error', message: 'Ce joueur existe déjà.' },
            });
        });

        const alert = await screen.findByRole('alert');

        expect(alert.textContent).toBe('Ce joueur existe déjà.');
        expect(screen.queryByRole('status')).toBeNull();
        expect(
            alert
                .closest('[data-sonner-toast]')
                ?.classList.contains('text-destructive'),
        ).toBe(true);
    });

    it('shows a confirmation in a status', async () => {
        render(<Toaster />);

        act(() => {
            showFlashToast({
                toast: { type: 'success', message: 'Round enregistré.' },
            });
        });

        const status = await screen.findByRole('status');

        expect(status.textContent).toBe('Round enregistré.');
        expect(screen.queryByRole('alert')).toBeNull();
        expect(
            status
                .closest('[data-sonner-toast]')
                ?.classList.contains('text-destructive'),
        ).toBe(false);
    });

    it('shows nothing for a flash without toast', () => {
        const { container } = render(<Toaster />);

        act(() => {
            showFlashToast({});
        });

        expect(container.ownerDocument.querySelectorAll('[data-sonner-toast]'))
            .toHaveLength(0);
    });

    it('removes the message with the close button', async () => {
        render(<Toaster />);

        act(() => {
            showFlashToast({
                toast: { type: 'success', message: 'Round enregistré.' },
            });
        });
        // A plain click: jsdom has no setPointerCapture, which sonner calls
        // on pointerdown.
        fireEvent.click(
            await screen.findByRole('button', { name: 'Fermer le message' }),
        );

        await waitFor(() => {
            expect(screen.queryByText('Round enregistré.')).toBeNull();
        });
    });
});
