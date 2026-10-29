import { router } from '@inertiajs/react';
import type { MockInstance } from 'vite-plus/test';
import { act, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';

import { DeleteRound } from '@/components/rounds/delete-round';
import { ROUND } from '@/tests/reference-round';

// Spec rounds « Suppression d'un round » (« Texte de la feuille »,
// « Suppression annulée ») and loading-feedback « Attentes des joueurs et des
// rounds ». The router's delete verb is stubbed (jsdom cannot run a visit); the
// visit callbacks it receives drive the sheet's own state. The orb paints on a
// canvas jsdom cannot draw.
// Mutations:
// - the date of the sheet written « 17 octobre 2026 » (formatPlayedOn) → the
//   text test goes red;
// - « Annuler » wired to the delete visit → the cancel test goes red;
// - « Annuler » put before « Supprimer » → the text test goes red;
// - the pending label without « … » → the deleting test goes red;
// - the action left enabled while deleting → the deleting test goes red.

vi.mock('thinking-orbs', () => ({
    ThinkingOrb: () => <canvas data-testid="orb" />,
}));

type VisitOptions = { onStart?: (visit: unknown) => void };

let remove: MockInstance<typeof router.delete>;

beforeEach(() => {
    remove = vi.spyOn(router, 'delete').mockImplementation(() => {});
});

afterEach(() => {
    vi.restoreAllMocks();
});

function sheet() {
    render(
        <DeleteRound
            roundId={ROUND}
            courseName="BETHPAGE BLACK"
            playedOn="2026-10-17"
        />,
    );
}

async function openSheet() {
    await userEvent.click(screen.getByRole('button', { name: 'Supprimer' }));

    return screen.getByRole('alertdialog');
}

describe('DeleteRound', () => {
    it('shows « Supprimer ce round ? », the course and the date, then « Supprimer » and « Annuler »', async () => {
        sheet();

        const dialog = await openSheet();

        expect(
            within(dialog).getByRole('heading', {
                name: 'Supprimer ce round ?',
            }),
        ).toBeInTheDocument();
        expect(
            within(dialog).getByText(
                'BETHPAGE BLACK · 17 oct. 2026. Les index de tous les joueurs seront recalculés.',
            ),
        ).toBeInTheDocument();
        expect(
            within(dialog)
                .getAllByRole('button')
                .map((button) => button.textContent),
        ).toEqual(['Supprimer', 'Annuler']);
        expect(remove).not.toHaveBeenCalled();
    });

    it('shows « Suppression du round… » and disables « Supprimer » while deleting', async () => {
        remove.mockImplementation((_url, options) => {
            (options as VisitOptions).onStart?.({});
        });
        sheet();

        const dialog = await openSheet();

        await act(async () => {
            await userEvent.click(
                within(dialog).getByRole('button', { name: 'Supprimer' }),
            );
        });

        expect(remove).toHaveBeenCalledTimes(1);
        expect(remove.mock.calls[0][0]).toBe(`/rounds/${ROUND}`);
        expect(within(dialog).getByRole('status').textContent).toBe(
            'Suppression du round…',
        );
        expect(
            within(dialog).getByRole('button', { name: 'Supprimer' }),
        ).toBeDisabled();
    });

    it('sends nothing when « Annuler » is touched', async () => {
        sheet();

        const dialog = await openSheet();

        await userEvent.click(
            within(dialog).getByRole('button', { name: 'Annuler' }),
        );

        expect(remove).not.toHaveBeenCalled();
    });
});
