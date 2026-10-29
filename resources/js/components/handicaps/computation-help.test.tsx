import { router } from '@inertiajs/react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vite-plus/test';

import {
    ComputationLink,
    RULE_LINES,
} from '@/components/handicaps/computation-help';
import { MANU } from '@/tests/reference-round';

// Spec handicap-index « Lien vers le calcul de l'index » and « Lignes de règle
// du calcul de l'index » (design D6 of index-computation-page). The seven
// lines are hand-copied from the spec scenario « Texte exact des lignes », in
// the order of the steps.
// Mutations: two lines swapped (the former order, « Index » before
// « Différentiels retenus ») or one word changed (« par + 3 », the straight
// apostrophe in « Différentiel d’un round ») → the RULE_LINES test goes red;
// `?joueur` dropped when the player is given → the player test goes red; the
// link sent to `/handicaps` → both href tests go red; the lines rendered under
// the link → the no-line test goes red; the link written as a <span onClick>
// → the keyboard test goes red.

const LINES = [
    'Score ajusté : chaque trou compte au plus par + 4.',
    'Différentiel d’un round : 113 / slope × (score ajusté − course rating), arrondi au dixième.',
    'Différentiels retenus : 1 de 3 à 5 rounds, 2 de 6 à 8, 3 de 9 à 11, 4 de 12 à 14, 5 à 15 ou 16, 6 à 17 ou 18, 7 à 19, 8 à 20.',
    'Ajustement : −2,0 à 3 rounds, −1,0 à 4 rounds et à 6 rounds, aucun sinon.',
    'Index : moyenne des meilleurs différentiels parmi les 20 derniers rounds, arrondie au dixième et plafonnée à 54,0.',
    'Index provisoire à 1 ou 2 rounds : moyenne de leurs différentiels, sans ajustement.',
    'Parcours sans rating : la course rating vaut le par du round et le slope 113.',
];

function link(): HTMLElement {
    return screen.getByRole('link', { name: 'Comment l’index est calculé' });
}

describe('RULE_LINES', () => {
    it('holds the seven lines of the spec, word for word, in the order of the steps', () => {
        expect([...RULE_LINES]).toEqual(LINES);
    });
});

describe('ComputationLink', () => {
    it('links to /handicaps/calcul without a player', () => {
        render(<ComputationLink />);

        expect(link().getAttribute('href')).toBe('/handicaps/calcul');
    });

    it('keeps the given player in ?joueur=', () => {
        render(<ComputationLink playerId={MANU} />);

        expect(link().getAttribute('href')).toBe(
            `/handicaps/calcul?joueur=${MANU}`,
        );
    });

    it('renders none of the seven rule lines', () => {
        const { container } = render(<ComputationLink playerId={MANU} />);

        expect(container.textContent).toBe('Comment l’index est calculé');
        for (const line of LINES) {
            expect(screen.queryByText(line)).toBeNull();
        }
    });

    // D6: text-link-13 in grey, underlined on hover, 44 px high.
    it('writes the link at text-link-13 in ink-2, 44 px high, underlined on hover', () => {
        render(<ComputationLink />);

        expect(link()).toHaveClass(
            'text-link-13',
            'text-ink-2',
            'min-h-11',
            'hover:underline',
        );
    });

    // Scenario « Ouvert au clavier »: Tab reaches the link, Enter opens the
    // page through the Inertia router.
    it('opens the page with Tab then Enter', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        render(<ComputationLink />);

        await userEvent.tab();

        expect(document.activeElement).toBe(link());

        await userEvent.keyboard('{Enter}');

        expect(visit).toHaveBeenCalledTimes(1);
        expect(visit.mock.calls[0][0]).toBe('/handicaps/calcul');
        visit.mockRestore();
    });
});
