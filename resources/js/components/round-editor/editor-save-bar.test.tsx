import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vite-plus/test';

import { EditorSaveBar } from '@/components/round-editor/editor-save-bar';

// Spec rounds « Barre d'enregistrement de l'éditeur » (« Problèmes restants »,
// « Carte cohérente ») and loading-feedback « Attentes de l'éditeur de
// round ». The orb paints on a canvas jsdom cannot draw; jsdom has no
// matchMedia (read by the orb for reduced motion).
// Mutations:
// - the button left enabled with a problem → the problems test goes red;
// - the messages joined by « , » instead of « · » → the two messages test
//   goes red;
// - the messages painted `text-ink-2` instead of `text-accent` → the problems
//   test goes red;
// - « Enregistrer » enabled while saving → the saving test goes red.

vi.mock('thinking-orbs', () => ({
    ThinkingOrb: () => <canvas data-testid="orb" />,
}));

vi.stubGlobal('matchMedia', () => ({
    matches: false,
    addEventListener: () => {},
    removeEventListener: () => {},
}));

const unassigned = { message: 'Ligne sans joueur', cells: ['player0:player'] };

describe('EditorSaveBar', () => {
    // Spec visual-theme « Animations du design », « Bouton d'enregistrement »
    // and « Appui »: fill and text change in 300 ms (`ease`), the press scales
    // to 0,98. Mutation: the 150 ms of the other buttons kept
    // (`duration-(--dur-press)`) → this test goes red.
    it('turns « Enregistrer » on in 300 ms and presses it to 0,98', () => {
        render(
            <EditorSaveBar problems={[]} saving={false} onSave={() => {}} />,
        );

        const button = screen.getByRole('button', { name: 'Enregistrer' });

        expect(button).toHaveClass(
            'duration-(--dur-swap)',
            'ease-[ease]',
            'active:not-disabled:scale-[0.98]',
        );
        expect(button).not.toHaveClass('duration-(--dur-press)');
    });

    it('shows the message of the line without player in amber and the blocked « 1 problème à corriger »', () => {
        render(
            <EditorSaveBar
                problems={[unassigned]}
                saving={false}
                onSave={() => {}}
            />,
        );

        const messages = screen.getByTestId('problem-messages');
        const button = screen.getByRole('button', {
            name: '1 problème à corriger',
        });

        expect(messages.textContent).toBe('Ligne sans joueur');
        expect(messages.parentElement).toHaveClass('text-accent');
        expect(button).toBeDisabled();
        expect(button).toHaveClass(
            'h-14',
            'rounded-14',
            'disabled:bg-surface-3',
            'disabled:text-ink-3',
        );
    });

    it('joins the remaining messages by « · », the identical ones counted', () => {
        render(
            <EditorSaveBar
                problems={[
                    unassigned,
                    { message: 'Case vide', cells: ['player1:3'] },
                    { message: 'Case vide', cells: ['player1:4'] },
                ]}
                saving={false}
                onSave={() => {}}
            />,
        );

        expect(screen.getByTestId('problem-messages').textContent).toBe(
            'Ligne sans joueur · Case vide (×2)',
        );
        expect(
            screen.getByRole('button', { name: '3 problèmes à corriger' }),
        ).toBeDisabled();
    });

    it('shows no message and « Enregistrer » in ink on a coherent card', async () => {
        const onSave = vi.fn();

        render(<EditorSaveBar problems={[]} saving={false} onSave={onSave} />);

        const button = screen.getByRole('button', { name: 'Enregistrer' });

        expect(screen.queryByTestId('problem-messages')).toBeNull();
        expect(button).toBeEnabled();
        expect(button).toHaveClass('bg-ink', 'text-bg');

        await userEvent.click(button);

        expect(onSave).toHaveBeenCalledTimes(1);
    });

    it('shows « Enregistrement du round… » and disables the button while saving', () => {
        render(<EditorSaveBar problems={[]} saving onSave={() => {}} />);

        expect(screen.getByRole('status').textContent).toBe(
            'Enregistrement du round…',
        );
        expect(
            screen.getByRole('button', { name: 'Enregistrer' }),
        ).toBeDisabled();
    });
});
