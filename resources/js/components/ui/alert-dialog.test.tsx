import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vite-plus/test';

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';

// Spec app-shell « Feuille de confirmation » (« Voile touché », « Échap »),
// visual-theme « Contraste sur le thème sombre » (« Bouton de suppression »);
// design D5. jsdom computes no layout: the classes that size and colour the
// buttons are the contract.
// Mutations: the scrim calls the action on a tap → the scrim test goes red;
// focus not given back to the trigger (`finalFocus={false}` on the popup) →
// the Escape test goes red; the destructive variant back to a red outline →
// the colour test goes red.

function Sheet({ onConfirm }: { onConfirm: () => void }) {
    return (
        <AlertDialog>
            <AlertDialogTrigger render={<Button variant="destructive" />}>
                Supprimer
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogTitle>Supprimer ce round ?</AlertDialogTitle>
                <AlertDialogDescription>
                    BETHPAGE BLACK · 17 oct. 2026. Les index de tous les
                    joueurs seront recalculés.
                </AlertDialogDescription>
                <AlertDialogFooter>
                    <AlertDialogAction variant="destructive" onClick={onConfirm}>
                        Supprimer
                    </AlertDialogAction>
                    <AlertDialogCancel>Annuler</AlertDialogCancel>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

async function open(onConfirm = vi.fn()) {
    render(<Sheet onConfirm={onConfirm} />);
    await userEvent.click(screen.getByRole('button', { name: 'Supprimer' }));

    return { onConfirm, sheet: await screen.findByRole('alertdialog') };
}

function trigger() {
    return document.querySelector<HTMLElement>(
        '[data-slot="alert-dialog-trigger"]',
    )!;
}

describe('confirmation sheet', () => {
    it('opens as an alertdialog named by its title and described by its text', async () => {
        const { sheet } = await open();

        expect(sheet).toHaveAccessibleName('Supprimer ce round ?');
        expect(sheet).toHaveAccessibleDescription(
            'BETHPAGE BLACK · 17 oct. 2026. Les index de tous les joueurs seront recalculés.',
        );
    });

    it('offers the action then « Annuler », both 52 px high', async () => {
        const { sheet } = await open();
        const buttons = [...sheet.querySelectorAll('button')];

        expect(buttons.map((button) => button.textContent)).toEqual([
            'Supprimer',
            'Annuler',
        ]);
        buttons.forEach((button) => expect(button).toHaveClass('h-13'));
    });

    it('writes the destructive action in white on the red fill', async () => {
        const { sheet } = await open();
        const action = sheet.querySelector('[data-slot="alert-dialog-action"]');

        expect(action).toHaveClass('bg-signal', 'text-on-signal');
    });

    // B3, DO33: a destructive sheet opens on « Annuler », so Enter then Enter
    // from the keyboard never confirms. Mutation: `initialFocus` dropped from
    // AlertDialogContent (base-ui focuses the first button, the action) →
    // this test goes red.
    it('puts the focus on « Annuler » when opened from the keyboard, Enter then cancelling', async () => {
        const onConfirm = vi.fn();
        render(<Sheet onConfirm={onConfirm} />);
        trigger().focus();

        await userEvent.keyboard('{Enter}');
        const sheet = await screen.findByRole('alertdialog');

        await waitFor(() => {
            expect(document.activeElement).toBe(
                sheet.querySelector('[data-slot="alert-dialog-cancel"]'),
            );
        });
        await userEvent.keyboard('{Enter}');
        await waitFor(() => {
            expect(screen.queryByRole('alertdialog')).toBeNull();
        });
        expect(onConfirm).not.toHaveBeenCalled();
    });

    it('closes on Escape without the action, focus back on the trigger', async () => {
        const { onConfirm } = await open();

        await userEvent.keyboard('{Escape}');

        await waitFor(() => {
            expect(screen.queryByRole('alertdialog')).toBeNull();
        });
        expect(onConfirm).not.toHaveBeenCalled();
        expect(document.activeElement).toBe(trigger());
    });

    it('closes on a tap on the scrim without the action', async () => {
        const { onConfirm } = await open();
        const scrim = document.querySelector<HTMLElement>(
            '[data-slot="alert-dialog-overlay"]',
        )!;

        act(() => {
            fireEvent.pointerDown(scrim);
            fireEvent.mouseDown(scrim);
            fireEvent.pointerUp(scrim);
            fireEvent.mouseUp(scrim);
            fireEvent.click(scrim);
        });

        await waitFor(() => {
            expect(screen.queryByRole('alertdialog')).toBeNull();
        });
        expect(onConfirm).not.toHaveBeenCalled();
    });
});
