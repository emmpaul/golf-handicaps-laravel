import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';

import { ComputationHelp } from '@/components/handicaps/computation-help';

// Spec handicap-index « Explication du calcul » and « Texte de l'explication du
// calcul »: the seven lines, hand-copied from the spec scenario « Texte exact ».
// Mutations: « slope / 113 » instead of « 113 / slope », « par + 3 », « 2 de 6
// à 8 » changed to « 2 de 7 à 8 », the 6-round adjustment dropped, or the
// straight apostrophe in « Différentiel d’un round » → the text test goes red;
// the block open by default → the closed test goes red; `text-sm` kept on
// the block or `text-muted-foreground` on its lines (sizes outside the closed
// scale of the design, which produce no style, DO26) → the scale test goes red.

const LINES = [
    'Score ajusté : chaque trou compte au plus par + 4.',
    'Différentiel d’un round : 113 / slope × (score ajusté − course rating), arrondi au dixième.',
    'Index : moyenne des meilleurs différentiels parmi les 20 derniers rounds, arrondie au dixième et plafonnée à 54,0.',
    'Différentiels retenus : 1 de 3 à 5 rounds, 2 de 6 à 8, 3 de 9 à 11, 4 de 12 à 14, 5 à 15 ou 16, 6 à 17 ou 18, 7 à 19, 8 à 20.',
    'Ajustement : −2,0 à 3 rounds, −1,0 à 4 rounds et à 6 rounds, aucun sinon.',
    'Index provisoire à 1 ou 2 rounds : moyenne de leurs différentiels, sans ajustement.',
    'Parcours sans rating : la course rating vaut le par du round et le slope 113.',
];

/** The `<details>` holding the explanation. */
function details(): HTMLDetailsElement {
    return screen
        .getByText('Comment l’index est calculé')
        .closest('details') as HTMLDetailsElement;
}

describe('ComputationHelp', () => {
    // DO26: the sizes of the design scale (D2), no class outside it.
    it('writes the title at 13 px Geist 500 and the lines at 12 px in ink-2', () => {
        render(<ComputationHelp />);

        const summary = screen.getByText('Comment l’index est calculé');
        const list = details().querySelector('ul') as HTMLElement;
        const classes = [
            details().className,
            summary.className,
            list.className,
        ].join(' ');

        expect(summary.className).toContain('text-link-13');
        expect(summary.className).toContain('min-h-11');
        expect(list.className).toContain('text-body-12');
        expect(list.className).toContain('text-ink-2');
        expect(details().className).toContain('border-rule');
        expect(
            classes
                .split(/\s+/)
                .filter((name) =>
                    /^(max-md:|md:)?(text-(sm|xs|base|lg)|text-muted-foreground|max-w-2xl)$/.test(
                        name,
                    ),
                ),
        ).toEqual([]);
    });

    it('renders closed, titled « Comment l’index est calculé », no line visible', () => {
        render(<ComputationHelp />);

        expect(details().open).toBe(false);
        expect(details().hasAttribute('open')).toBe(false);
        expect(screen.getByText('Comment l’index est calculé').tagName).toBe(
            'SUMMARY',
        );

        for (const line of LINES) {
            expect(screen.getByText(line)).not.toBeVisible();
        }
    });

    it('shows the seven lines of the spec, in order, after a click on the title', async () => {
        render(<ComputationHelp />);

        await userEvent.click(screen.getByText('Comment l’index est calculé'));

        expect(details().open).toBe(true);

        const items = [...details().querySelectorAll('li')];

        expect(items.map((item) => item.textContent)).toEqual(LINES);
        for (const item of items) {
            expect(item).toBeVisible();
        }
    });

    // The browser opens a focused native <summary> on Enter; user-event does
    // not simulate that activation (it clicks only buttons, inputs and links on
    // Enter), so the test proves the keyboard path up to the native control:
    // Tab reaches the title, and the title is the <summary> of the <details>.
    // The Enter key itself is checked in the browser pass (tasks.md 9.2).
    // Mutation: the title written as a <div onClick> → Tab skips it, red.
    it('reaches the title with Tab, as the native summary of the block', async () => {
        render(<ComputationHelp />);

        await userEvent.tab();

        const title = screen.getByText('Comment l’index est calculé');

        expect(document.activeElement).toBe(title);
        expect(details().querySelector(':scope > summary')).toBe(title);
    });
});
